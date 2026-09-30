<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * PHP-23 — code names what it needs, never the backend behind it.
 *
 * Flags a backend name passed as a string literal to the calls that pick a queue connection, disk,
 * cache store, log channel, mailer or broadcaster, a `$connection` property set to one, and a
 * reference to a backend-specific class such as the AWS SDK. Only literals are checked: a name
 * read from config is exactly what the rule asks for.
 *
 * The driver classes that wrap a backend, and `extend()` driver registrations, are the one place
 * allowed to name it.
 */
final class BackendNames extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'routes', 'database', 'bootstrap'];

    /**
     * The calls whose first argument names a connection, disk, store, channel or mailer.
     */
    private const METHODS = ['onConnection', 'connection', 'disk', 'drive', 'store', 'driver', 'channel', 'stack', 'mailer'];

    /**
     * Driver names, as they appear in config/. `public` is absent on purpose: it names what the
     * files are for, not where they live.
     */
    private const BACKENDS = [
        // Queues
        'sqs', 'sqs-fifo', 'redis', 'database', 'beanstalkd', 'sync',
        // Files
        'local', 's3', 'ftp', 'sftp',
        // Cache
        'file', 'memcached', 'dynamodb', 'apc', 'octane', 'array',
        // Logs
        'single', 'daily', 'monthly', 'stderr', 'syslog', 'errorlog', 'slack', 'papertrail', 'cloud',
        // Mail
        'smtp', 'ses', 'postmark', 'resend', 'mailgun', 'sendmail', 'log',
        // Broadcasting
        'pusher', 'ably', 'reverb', 'mercure',
    ];

    /**
     * Backend-specific classes, as exact names or namespace prefixes ending in `\`.
     */
    private const BACKEND_CLASSES = [
        'Aws\\',
        'League\\Flysystem\\AwsS3V3\\',
        'Illuminate\\Queue\\SqsQueue',
        'Illuminate\\Filesystem\\AwsS3V3Adapter',
    ];

    /**
     * Calls that register a driver, whose arguments may name the backend they wrap.
     */
    private const REGISTRATIONS = ['extend', 'addConnector'];

    /**
     * Where a backend may be named: the drivers that wrap one.
     */
    private const DRIVER_FOLDERS = ['app/Queue/', 'app/Logging/Drivers/'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Code names what it needs, never the backend behind it (PHP-23)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            if ($this->isDriver($this->relative($path))) {
                continue;
            }

            $registrations = $this->insideRegistrations($path);

            $literals = $this->findIn($path, fn (Node $node): bool => ! isset($registrations[spl_object_id($node)])
                && $this->backendNamed($node) !== null);

            foreach ($literals as $node) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d names the `%s` backend. Use the default or a logical name, and let '
                        . 'config/ pick the driver from env (PHP-23).',
                        $node->getStartLine(),
                        $this->backendNamed($node)
                    ));
            }

            $reported = [];

            /** @var FullyQualified[] $classes */
            $classes = $this->findIn($path, fn (Node $node): bool => $node instanceof FullyQualified
                && ! isset($registrations[spl_object_id($node)])
                && $this->isBackendClass($node->toString()));

            foreach ($classes as $class) {
                if (isset($reported[$class->toString()])) {
                    continue;
                }

                $reported[$class->toString()] = true;
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d uses `%s`, which only exists on one backend. Depend on the Laravel '
                        . 'contract here, and keep the class inside a driver (app/Queue/, '
                        . 'app/Logging/Drivers/) registered with extend() (PHP-23).',
                        $class->getStartLine(),
                        $class->toString()
                    ));
            }
        }

        return $details;
    }

    private function isDriver(string $relative): bool
    {
        foreach (self::DRIVER_FOLDERS as $folder) {
            if (str_starts_with($relative, $folder)) {
                return true;
            }
        }

        return false;
    }

    private function isBackendClass(string $name): bool
    {
        foreach (self::BACKEND_CLASSES as $backend) {
            if (str_ends_with($backend, '\\') ? str_starts_with($name, $backend) : $name === $backend) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every node inside the arguments of a driver registration, by object id.
     *
     * @return array<int, true>
     */
    private function insideRegistrations(string $path): array
    {
        $inside = [];
        $finder = new NodeFinder();

        $registrations = $this->findIn($path, static fn (Node $node): bool => ($node instanceof MethodCall || $node instanceof StaticCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Identifier && in_array($node->name->toString(), self::REGISTRATIONS, true));

        foreach ($registrations as $registration) {
            foreach ($finder->find($registration->args, static fn (): bool => true) as $node) {
                $inside[spl_object_id($node)] = true;
            }
        }

        return $inside;
    }

    /**
     * The backend a node names, or null when it names none.
     */
    private function backendNamed(Node $node): ?string
    {
        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) {
            if (! $node->name instanceof Identifier || ! in_array($node->name->toString(), self::METHODS, true)) {
                return null;
            }

            $first = $node->args[0] ?? null;

            return $first instanceof Arg ? $this->backendIn($first->value) : null;
        }

        // public string $connection = 'sqs';
        if ($node instanceof Property) {
            foreach ($node->props as $property) {
                if ($property->name->toString() === 'connection' && $property->default !== null) {
                    return $this->backendIn($property->default);
                }
            }

            return null;
        }

        // $this->connection = 'sqs';
        if ($node instanceof Assign && $node->var instanceof PropertyFetch
            && $node->var->name instanceof Identifier && $node->var->name->toString() === 'connection') {
            return $this->backendIn($node->expr);
        }

        return null;
    }

    private function backendIn(Node $value): ?string
    {
        if ($value instanceof String_) {
            return in_array($value->value, self::BACKENDS, true) ? $value->value : null;
        }

        // Log::stack(['single', 'cloud'])
        if ($value instanceof Array_) {
            foreach ($value->items as $item) {
                if ($item !== null && ($backend = $this->backendIn($item->value)) !== null) {
                    return $backend;
                }
            }
        }

        return null;
    }
}
