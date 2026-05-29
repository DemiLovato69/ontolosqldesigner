<?php

declare(strict_types=1);

namespace App\Services\SqlDialects;

class MysqlDialect implements SqlDialectInterface
{
    public function quote(string $identifier): string
    {
        return "`{$identifier}`";
    }

    public function supportsIfNotExists(): bool
    {
        return true;
    }

    public function supportsFulltext(): bool
    {
        return true;
    }

    public function usesInlineForeignKeys(): bool
    {
        return false;
    }

    public function supportsUnsigned(): bool
    {
        return true;
    }

    public function supportsColumnComment(): bool
    {
        return true;
    }

    public function uniqueConstraintSql(string $constraintName, array $columns): string
    {
        $quotedCols = implode(', ', array_map(fn ($c) => $this->quote($c), $columns));

        return 'UNIQUE KEY '.$this->quote($constraintName)." ({$quotedCols})";
    }

    public function fulltextIndexSql(string $indexName, array $columns): string
    {
        $quotedCols = implode(', ', array_map(fn ($c) => $this->quote($c), $columns));

        return 'FULLTEXT KEY '.$this->quote($indexName)." ({$quotedCols})";
    }
}
