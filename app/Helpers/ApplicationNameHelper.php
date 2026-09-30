<?php

declare(strict_types=1);

namespace App\Helpers;

class ApplicationNameHelper
{
    private static ?string $applicationName = null;

    /**
     * The application's name, read once from the project's composer.json (PHP-22).
     */
    public static function name(): string
    {
        return self::$applicationName ??= self::fromComposerManifest(base_path('composer.json'));
    }

    /**
     * The vendor part of the `name` in the given composer.json.
     */
    public static function fromComposerManifest(string $manifestPath): string
    {
        $manifest = json_decode((string) file_get_contents($manifestPath), associative: true, flags: JSON_THROW_ON_ERROR);

        return explode('/', (string) $manifest['name'])[0];
    }
}
