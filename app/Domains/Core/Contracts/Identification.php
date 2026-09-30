<?php

declare(strict_types=1);

namespace App\Domains\Core\Contracts;

/**
 * A strongly typed unique identifier for a particular entity.
 *
 * The actual {@see self::$value} can be of ANY type but still wrapped
 * in a recognizable and specific identification class.
 */
abstract class Identification
{
    public function __construct(
        public readonly mixed $value,
    ) {}
}
