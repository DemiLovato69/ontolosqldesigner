<?php

declare(strict_types=1);

namespace App\Enums;

enum VisitorStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REVOKED = 'revoked';
}
