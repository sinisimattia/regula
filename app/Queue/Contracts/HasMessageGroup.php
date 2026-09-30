<?php

declare(strict_types=1);

namespace App\Queue\Contracts;

interface HasMessageGroup
{
    public function messageGroupId(): string;

    public function messageDeduplicationId(): string;
}
