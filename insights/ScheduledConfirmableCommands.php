<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Schedule;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;

/**
 * DB-14 — a command that asks for confirmation is scheduled with `--force` in its command string.
 *
 * Covers the commands declared under app/ that use `ConfirmableTrait`, scheduled from
 * app/Console/Kernel.php or routes/console.php. A framework command such as `migrate` is not
 * recognised, because its name cannot be resolved without booting the console.
 */
final class ScheduledConfirmableCommands extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const SCHEDULERS = ['app/Console/Kernel.php', 'routes/console.php'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A command using ConfirmableTrait is scheduled with --force in its command string (DB-14)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $confirmable = $this->confirmableCommands();

        if ($confirmable === []) {
            return [];
        }

        $details = [];

        foreach (self::SCHEDULERS as $scheduler) {
            $path = getcwd() . '/' . $scheduler;

            if (! is_file($path) || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->findIn($path, $this->isScheduling(...)) as $call) {
                /** @var MethodCall|StaticCall $call */
                $argument = $call->getArgs()[0]->value ?? null;

                if ($argument === null) {
                    continue;
                }

                [$text, $class] = $this->commandString($argument);
                $first = explode(' ', trim($text))[0];
                $name = $class !== null ? ($confirmable[$class] ?? null) : null;
                $name ??= in_array($first, $confirmable, true) ? $first : null;

                if ($name === null || preg_match('/(^|\s)--force(\s|=|$)/', $text) === 1) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d schedules `%s` without `--force`. It asks for confirmation, so it aborts '
                        . 'silently on every scheduled run in production. Put `--force` in the command '
                        . 'string, not the parameters array (DB-14).',
                        $call->getStartLine(),
                        $name
                    ));
            }
        }

        return $details;
    }

    private function isScheduling(Node $node): bool
    {
        if ($node instanceof MethodCall) {
            return $node->name instanceof Identifier && $node->name->toString() === 'command';
        }

        return $node instanceof StaticCall
            && $node->class instanceof Name
            && $node->class->toString() === Schedule::class
            && $node->name instanceof Identifier
            && $node->name->toString() === 'command';
    }

    /**
     * The literal text of a scheduled command, and the command class it names, if any.
     *
     * @return array{0: string, 1: string|null}
     */
    private function commandString(Expr $expression): array
    {
        if ($expression instanceof String_) {
            return [$expression->value, null];
        }

        if ($expression instanceof ClassConstFetch && $expression->class instanceof Name) {
            return ['', $expression->class->toString()];
        }

        if ($expression instanceof Concat) {
            [$leftText, $leftClass] = $this->commandString($expression->left);
            [$rightText] = $this->commandString($expression->right);

            return [$leftText . $rightText, $leftClass];
        }

        return ['', null];
    }

    /**
     * Commands under app/ that use `ConfirmableTrait`, their names keyed by class.
     *
     * @return array<string, string>
     */
    private function confirmableCommands(): array
    {
        $commands = [];

        foreach ($this->classIndex() as $fqcn => ['node' => $class]) {
            if (! $class instanceof Class_) {
                continue;
            }

            $traits = [];

            foreach ($class->stmts as $statement) {
                if ($statement instanceof TraitUse) {
                    foreach ($statement->traits as $trait) {
                        $traits[] = $trait->toString();
                    }
                }
            }

            if (! in_array(ConfirmableTrait::class, $traits, true)) {
                continue;
            }

            $name = $this->commandName($class);

            if ($name !== null) {
                $commands[$fqcn] = $name;
            }
        }

        return $commands;
    }

    private function commandName(Class_ $class): ?string
    {
        foreach (['signature', 'name'] as $property) {
            $default = $class->getProperty($property)?->props[0]->default;

            if ($default instanceof String_ && trim($default->value) !== '') {
                return (string) preg_split('/[\s{]/', trim($default->value))[0];
            }
        }

        return null;
    }
}
