<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use SimpleXMLElement;

/**
 * TEST-13 — no test reaches an external service.
 *
 * Checks the guards that make that the default: `Http::preventStrayRequests()` in the base
 * `TestCase`, and phpunit.xml forcing the local queue, mail and broadcast drivers. Flags an AWS
 * SDK client built for real in tests/; a mocked one is fine.
 */
final class NoExternalServicesInTests extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const TEST_CASE = 'tests/TestCase.php';

    private const HTTP_FACADES = ['Illuminate\Support\Facades\Http', 'Http'];

    /**
     * env name => the local value phpunit.xml must force.
     */
    private const FORCED_ENV = [
        'QUEUE_DRIVER' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
    ];

    public function getTitle(): string
    {
        return 'No test reaches an external service (TEST-13)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $details = [];
        $testCase = $root . '/' . self::TEST_CASE;

        if (! is_file($testCase) || $this->findIn($testCase, fn (Node $node): bool => $this->preventsStrayRequests($node)) === []) {
            $details[] = $this->detail($testCase, 'The base TestCase does not call Http::preventStrayRequests(). Call it '
                . 'in setUp(), so an unfaked HTTP request fails the test instead of leaving the machine (TEST-13).');
        }

        foreach ($this->unforcedEnv($root . '/phpunit.xml') as $name => $value) {
            $details[] = $this->detail($root . '/phpunit.xml', sprintf(
                'phpunit.xml does not force %s to `%s`. Add `<env name="%1$s" value="%2$s" force="true"/>`, '
                . 'so a developer\'s shell cannot point the suite at a real backend (TEST-13).',
                $name,
                $value,
            ));
        }

        foreach ($this->filesUnder(['tests']) as $path) {
            foreach ($this->findIn($path, fn (Node $node): bool => $this->buildsAwsClient($node)) as $new) {
                /** @var New_ $new */
                $details[] = $this->detail($path, sprintf(
                    'Line %d builds a real %s, which talks to AWS. Mock it with Mockery or $this->mock(), '
                    . 'or use the Laravel fake for the service (TEST-13).',
                    $new->getStartLine(),
                    $new->class instanceof Name ? $new->class->getLast() : 'AWS client',
                ));
            }
        }

        return $details;
    }

    private function preventsStrayRequests(Node $node): bool
    {
        return $node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier
            && in_array($node->class->toString(), self::HTTP_FACADES, true)
            && $node->name->toLowerString() === 'preventstrayrequests';
    }

    private function buildsAwsClient(Node $node): bool
    {
        if (! $node instanceof New_ || ! $node->class instanceof Name) {
            return false;
        }

        $class = ltrim($node->class->toString(), '\\');

        return str_starts_with($class, 'Aws\\') && (str_ends_with($class, 'Client') || $class === 'Aws\Sdk');
    }

    /**
     * @return array<string, string>
     */
    private function unforcedEnv(string $phpunit): array
    {
        $missing = self::FORCED_ENV;
        $xml = is_file($phpunit) ? @simplexml_load_file($phpunit) : false;

        if (! $xml instanceof SimpleXMLElement) {
            return $missing;
        }

        foreach ($xml->xpath('/phpunit/php/env') ?: [] as $env) {
            $name = (string) $env['name'];

            if (isset($missing[$name]) && (string) $env['value'] === $missing[$name] && strtolower((string) $env['force']) === 'true') {
                unset($missing[$name]);
            }
        }

        return $missing;
    }
}
