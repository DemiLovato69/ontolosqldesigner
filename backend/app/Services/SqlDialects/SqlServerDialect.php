<?php

declare(strict_types=1);

namespace App\Services\SqlDialects;

class SqlServerDialect implements SqlDialectInterface
{
    public function quote(string $identifier): string
    {
        return "[{$identifier}]";
    }

    public function supportsIfNotExists(): bool
    {
        return false;
    }

    public function supportsFulltext(): bool
    {
        return false;
    }

    public function usesInlineForeignKeys(): bool
    {
        return false;
    }

    public function supportsUnsigned(): bool
    {
        return false;
    }

    public function supportsColumnComment(): bool
    {
        return false;
    }

    public function uniqueConstraintSql(string $constraintName, array $columns): string
    {
        $quotedCols = implode(', ', array_map(fn ($c) => $this->quote($c), $columns));

        return 'CONSTRAINT '.$this->quote($constraintName)." UNIQUE ({$quotedCols})";
    }

    public function fulltextIndexSql(string $indexName, array $columns): string
    {
        return '';
    }
}
