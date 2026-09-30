<?php

declare(strict_types=1);

namespace App\Queue\Concerns;

use Illuminate\Support\Str;

trait UsesOrderedQueue
{
    public string $connection = 'default-ordered';

    private ?string $resolvedDeduplicationId = null;

    public function messageDeduplicationId(): string
    {
        return $this->resolvedDeduplicationId ??= (string) Str::orderedUuid();
    }
}
