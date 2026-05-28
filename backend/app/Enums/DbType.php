<?php

namespace App\Enums;

enum DbType: string
{
    case MYSQL      = 'mysql';
    case POSTGRESQL = 'postgresql';
    case SQLITE     = 'sqlite';
    case ORACLE     = 'oracle';
    case SQLSERVER  = 'sqlserver';
    case MSACCESS   = 'msaccess';
}
