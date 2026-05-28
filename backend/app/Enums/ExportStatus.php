<?php

namespace App\Enums;

enum ExportStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case DONE       = 'done';
    case FAILED     = 'failed';
}
