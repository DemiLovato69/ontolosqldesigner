<?php

declare(strict_types=1);

namespace App\Enums;

enum DbType: string
{
    case MYSQL = 'mysql';
    case POSTGRESQL = 'postgresql';
    case SQLITE = 'sqlite';
    case ORACLE = 'oracle';
    case SQLSERVER = 'sqlserver';
    case MSACCESS = 'msaccess';
    case ONTOLOGY = 'ontology';
}
