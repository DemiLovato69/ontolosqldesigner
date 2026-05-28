<?php

namespace App\Enums;

enum DiagramAccess: string
{
    case READ     = 'read';
    case WRITE    = 'write';
    case PER_USER = 'per_user';
    case REVOKED  = 'revoked';
}
