<?php

declare(strict_types=1);

namespace App\Domains\Auth\Enums;

enum TokenAbility: string
{
    case IssueAccessToken = 'issue-access-token';
    case AccessApi = 'access-api';
}
