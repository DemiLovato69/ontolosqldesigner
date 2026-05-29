<?php

declare(strict_types=1);

namespace App\Services\SqlDialects;

interface SqlDialectInterface
{
    public function quote(string $identifier): string;

    public function supportsIfNotExists(): bool;

    public function supportsFulltext(): bool;

    public function usesInlineForeignKeys(): bool;

    public function supportsUnsigned(): bool;

    public function supportsColumnComment(): bool;

    /** @param string[] $columns */
    public function uniqueConstraintSql(string $constraintName, array $columns): string;

    /** @param string[] $columns — only called when supportsFulltext() is true */
    public function fulltextIndexSql(string $indexName, array $columns): string;
}
