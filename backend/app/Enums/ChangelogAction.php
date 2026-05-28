<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangelogAction: string
{
    case IMPORT_SQL = 'import_sql';
    case EXPORT_SQL = 'export_sql';
    case TABLE_ADDED = 'table_added';
    case TABLE_REMOVED = 'table_removed';
    case COLUMN_ADDED = 'column_added';
    case COLUMN_REMOVED = 'column_removed';
    case INDEX_ADDED = 'index_added';
    case INDEX_REMOVED = 'index_removed';
    case FK_ADDED = 'fk_added';
    case FK_REMOVED = 'fk_removed';
}
