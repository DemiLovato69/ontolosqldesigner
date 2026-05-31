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

    /**
     * Returns a standalone SQL statement to declare an enum type before a table,
     * or '' if this dialect uses inline ENUM definitions.
     *
     * @param string $typeName   suggested type name (e.g. "orders_status")
     * @param string $enumValues raw comma-separated quoted values from ENUM(...), e.g. "'a','b'"
     */
    public function enumTypeDeclaration(string $typeName, string $enumValues): string;

    /**
     * Returns the column type string to embed in CREATE TABLE for an ENUM column.
     * MySQL keeps the inline form; PostgreSQL returns the quoted type name.
     *
     * @param string $typeName suggested type name (matches enumTypeDeclaration)
     * @param string $sqlType  full original sql_type, e.g. "ENUM('a','b')"
     */
    public function enumColumnType(string $typeName, string $sqlType): string;
}
