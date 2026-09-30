<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;

/**
 * PHP-17 by inheritance: a class that extends or implements a framework kind carries that kind's
 * suffix wherever it lives. {@see ClassNaming} holds the kind's own folder, so a class there is
 * left to it rather than reported twice.
 */
final class ParentClassNaming extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    /**
     * @var list<array{ancestors: string[], suffixes: string[], folder: string}>
     */
    private const KINDS = [
        ['ancestors' => ['Illuminate\Console\Command'], 'suffixes' => ['Command'], 'folder' => '/Console/Commands/'],
        ['ancestors' => ['Illuminate\Foundation\Http\FormRequest'], 'suffixes' => ['Request'], 'folder' => '/Http/Requests/'],
        ['ancestors' => ['Illuminate\Http\Resources\Json\JsonResource'], 'suffixes' => ['Resource', 'ResourceCollection'], 'folder' => '/Http/Resources/'],
        ['ancestors' => ['Illuminate\Notifications\Notification'], 'suffixes' => ['Notification'], 'folder' => '/Notifications/'],
        ['ancestors' => ['Illuminate\Mail\Mailable'], 'suffixes' => ['Email'], 'folder' => '/Mail/'],
        ['ancestors' => ['Illuminate\Support\ServiceProvider'], 'suffixes' => ['Provider'], 'folder' => '/Providers/'],
        ['ancestors' => ['Throwable'], 'suffixes' => ['Exception'], 'folder' => '/Exceptions/'],
        [
            'ancestors' => ['Illuminate\Contracts\Validation\ValidationRule', 'Illuminate\Contracts\Validation\Rule', 'Illuminate\Contracts\Validation\InvokableRule'],
            'suffixes' => ['Rule'],
            'folder' => '/Rules/',
        ],
        [
            'ancestors' => ['Illuminate\Contracts\Database\Eloquent\CastsAttributes', 'Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes'],
            'suffixes' => ['Cast'],
            'folder' => '/Casts/',
        ],
        ['ancestors' => ['App\Http\Controllers\Controller', 'Illuminate\Routing\Controller'], 'suffixes' => ['Controller'], 'folder' => '/Http/Controllers/'],
    ];

    /**
     * Framework classes whose name Laravel fixes.
     */
    private const FRAMEWORK_FIXED = ['app/Exceptions/Handler.php'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A class extending a framework kind carries its suffix, wherever it lives (PHP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(['app']) as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, self::FRAMEWORK_FIXED, true)) {
                continue;
            }

            foreach ($this->classLikesIn($path) as $class) {
                if (! $class instanceof Class_ || $class->name === null) {
                    continue;
                }

                $name = $class->name->toString();
                $ancestors = $this->ancestorsOf($class);

                foreach (self::KINDS as $kind) {
                    if (array_intersect($kind['ancestors'], $ancestors) === []) {
                        continue;
                    }

                    if (! $this->endsWithAny($name, $kind['suffixes']) && ! str_contains('/' . $relative, $kind['folder'])) {
                        $details[] = Details::make()
                            ->setFile($path)
                            ->setMessage(sprintf(
                                '`%s` is a `%s`, so it must be named `{Noun}%s` (PHP-17).',
                                $name,
                                substr((string) strrchr('\\' . $kind['ancestors'][0], '\\'), 1),
                                $kind['suffixes'][0]
                            ));
                    }

                    break;
                }
            }
        }

        return $details;
    }

    /**
     * @param  string[]  $suffixes
     */
    private function endsWithAny(string $name, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
