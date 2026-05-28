<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case DONE       = 'done';
    case FAILED     = 'failed';
}
