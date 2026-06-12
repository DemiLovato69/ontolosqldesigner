<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChangelogAction;
use App\Enums\DbType;
use App\Enums\ExportStatus;
use App\Enums\ImportStatus;
use App\Events\SchemaImported;
use App\Exceptions\InvalidSchemaException;
use App\Jobs\ExportDiagramJob;
use App\Jobs\ImportDiagramSchemaJob;
use App\Models\Diagram;
use App\Models\DiagramChangelog;
use App\Models\User;
use App\Services\SqlDialects\MsAccessDialect;
use App\Services\SqlDialects\MysqlDialect;
use App\Services\SqlDialects\OracleDialect;
use App\Services\SqlDialects\PostgresqlDialect;
use App\Services\SqlDialects\SqlDialectInterface;
use App\Services\SqlDialects\SqliteDialect;
use App\Services\SqlDialects\SqlServerDialect;
use Illuminate\Support\Collection;
use ZipArchive;

class DiagramSqlService
{
    private const BACKUP_FORMAT = 'ontolosql-designer';

    private const BACKUP_VERSION = 1;

    public function __construct(
        private readonly OntologyMakerService $ontologyMakerService,
        private readonly HierarchicalDiagramLayoutService $layoutService,
    ) {}

    public function startImport(Diagram $diagram, string $script, User $user): void
    {
        $diagram->script = $script;
        $diagram->import_status = ImportStatus::PENDING;
        $diagram->import_error = null;
        $diagram->import_warnings = null;
        $diagram->save();

        ImportDiagramSchemaJob::dispatch($diagram);
        broadcast(new SchemaImported($diagram->share_token, (string) $user->id));

        DiagramChangelog::create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'user_name' => $user->email,
            'action' => ChangelogAction::IMPORT_SQL,
            'details' => null,
        ]);
    }

    public function startExport(Diagram $diagram, User $user): void
    {
        $diagram->export_status = ExportStatus::PENDING;
        $diagram->export_error = null;
        $diagram->script = null;
        $diagram->export_json = null;
        $diagram->save();

        ExportDiagramJob::dispatch($diagram);

        DiagramChangelog::create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'user_name' => $user->email,
            'action' => ChangelogAction::EXPORT_SQL,
            'details' => null,
        ]);
    }

    public function importSchema(Diagram $diagram, string $script): string
    {
        $payload = $this->createImportPayload($script, ($diagram->db_type ?? DbType::MYSQL)->value);
        $diagram->schema = $payload['schema'];
        $diagram->value_types = $payload['value_types'];
        if ($payload['db_type'] !== null) {
            $diagram->db_type = $payload['db_type'];
        }
        $diagram->import_warnings = $payload['warnings'];
        $diagram->save();

        return json_encode($diagram->schema);
    }

    public function exportScript(Diagram $diagram): string
    {
        $dbType = $diagram->db_type ?? DbType::MYSQL;
        $script = $dbType === DbType::ONTOLOGY
            ? $this->ontologyMakerService->createModule(json_encode($diagram->schema), $diagram->value_types ?? [])
            : $this->createScript(json_encode($diagram->schema), $dbType->value);
        $diagram->script = $script;
        $diagram->save();

        return $script;
    }

    public function createMigrationZip(Diagram $diagram): string
    {
        $files = $this->createMigration(json_encode($diagram->schema));
        $tmpPath = tempnam(sys_get_temp_dir(), 'migrations_');

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file) {
            $zip->addFromString("migrations/{$file['filename']}", $file['content']);
        }
        $zip->close();

        return $tmpPath;
    }

    // Time: O(N), Memory: O(N) — where N = total schema items (tables + rows + connections)
    public function createScript(string $schema, string $dbType = 'mysql'): string
    {
        $dialect = $this->resolveDialect($dbType);
        $qi = fn (string $name) => $dialect->quote($name);

        [$tables, $rows, $connections] = $this->parseSchemaItems($schema);
        $tablesById = $tables->keyBy('id');
        $rowsById = $rows->keyBy('id');
        $rowsByTable = $rows->groupBy('table_id');
        $lines = [];

        $inlineFksByTable = $dialect->usesInlineForeignKeys()
            ? $connections->groupBy(fn (array $c): string => $rowsById->get($c['target_id'])['table_id'] ?? '')
            : collect();

        foreach ($tables as $table) {
            $tableRows = $rowsByTable->get($table['id'], collect());
            $tableColumnNames = $tableRows->pluck('name')->all();

            // Collect enum type declarations (e.g. PostgreSQL CREATE TYPE ... AS ENUM)
            $enumTypeDecls = [];
            $enumTypeMap = []; // row_id => effective column type string
            foreach ($tableRows as $row) {
                if (preg_match('/^ENUM\s*\((.+)\)$/is', $row['sql_type'], $em)) {
                    $typeName = $table['name'].'_'.$row['name'];
                    $decl = $dialect->enumTypeDeclaration($typeName, $em[1]);
                    if ($decl !== '') {
                        $enumTypeDecls[] = $decl;
                    }
                    $enumTypeMap[$row['id']] = $dialect->enumColumnType($typeName, $row['sql_type']);
                }
            }

            $allDefs = $tableRows->map(function ($row) use ($dialect, $qi, $enumTypeMap) {
                $typeWord = strtoupper(preg_replace('/[\s(].*/', '', $row['sql_type']));
                if (in_array($typeWord, ['INDEX', 'KEY', 'CONSTRAINT', 'FOREIGN', 'CHECK', 'PRIMARY', 'UNIQUE'])) {
                    return null;
                }
                $colType = $enumTypeMap[$row['id']] ?? $row['sql_type'];
                $parts = ['  '.$qi($row['name'])." {$colType}"];
                if ($dialect->supportsUnsigned() && $row['unsigned']) {
                    $parts[] = 'UNSIGNED';
                }
                $parts[] = $row['nullable'] ? 'NULL' : 'NOT NULL';
                if ($row['key_mod'] && $row['key_mod'] !== 'FOREIGN KEY') {
                    $parts[] = $row['key_mod'];
                }
                if ($row['default_value'] !== null && $row['default_value'] !== '') {
                    $parts[] = "DEFAULT '{$row['default_value']}'";
                }
                if ($dialect->supportsColumnComment() && $row['comment'] !== null && $row['comment'] !== '') {
                    $parts[] = "COMMENT '".str_replace("'", "''", $row['comment'])."'";
                }

                return implode(' ', array_filter($parts));
            })->filter()->values()->all();

            foreach ($table['unique_together'] as $constraintCols) {
                $validCols = array_values(array_filter($constraintCols, fn ($c) => in_array($c, $tableColumnNames)));
                if (count($validCols) >= 2) {
                    $constraintName = 'uq_'.$table['name'].'_'.implode('_', $validCols);
                    $allDefs[] = '  '.$dialect->uniqueConstraintSql($constraintName, $validCols);
                }
            }

            if ($dialect->supportsFulltext()) {
                foreach ($table['fulltext_indexes'] as $ftCols) {
                    $validCols = array_values(array_filter($ftCols, fn ($c) => in_array($c, $tableColumnNames)));
                    if (count($validCols) >= 1) {
                        $indexName = 'ft_'.$table['name'].'_'.implode('_', $validCols);
                        $allDefs[] = '  '.$dialect->fulltextIndexSql($indexName, $validCols);
                    }
                }
            }

            if ($dialect->usesInlineForeignKeys()) {
                foreach ($inlineFksByTable->get($table['id'], collect()) as $connection) {
                    $sourceRow = $rowsById->get($connection['source_id']);
                    $targetRow = $rowsById->get($connection['target_id']);
                    if (! $sourceRow || ! $targetRow) {
                        continue;
                    }
                    $referencedTable = $tablesById->get($sourceRow['table_id'])['name'];
                    $allDefs[] = '  FOREIGN KEY ('.$qi($targetRow['name']).') REFERENCES '.$qi($referencedTable).'('.$qi($sourceRow['name']).')';
                }
            }

            foreach ($enumTypeDecls as $decl) {
                $lines[] = $decl;
            }
            $documentation = $this->sqlCommentLines($table['note'], "Table {$table['name']}");
            foreach ($tableRows as $row) {
                $documentation = array_merge($documentation, $this->sqlCommentLines((string) ($row['comment'] ?? ''), "Column {$table['name']}.{$row['name']}"));
            }
            if ($documentation !== []) {
                $lines[] = implode("\n", $documentation);
            }
            $ifNotExists = $dialect->supportsIfNotExists() ? 'IF NOT EXISTS ' : '';
            $lines[] = 'CREATE TABLE '.$ifNotExists.$qi($table['name'])." (\n".implode(",\n", $allDefs)."\n);";
        }

        if (! $dialect->usesInlineForeignKeys()) {
            foreach ($connections as $connection) {
                $sourceRow = $rowsById->get($connection['source_id']);
                $targetRow = $rowsById->get($connection['target_id']);
                if (! $sourceRow || ! $targetRow) {
                    continue;
                }
                $tableName = $tablesById->get($sourceRow['table_id'])['name'];
                $targetTableName = $tablesById->get($targetRow['table_id'])['name'];
                $lines[] = 'ALTER TABLE '.$qi($targetTableName)."\nADD FOREIGN KEY (".$qi($targetRow['name']).') REFERENCES '.$qi($tableName).'('.$qi($sourceRow['name']).');';
            }
        }

        return implode("\n\n", $lines).(count($lines) > 0 ? "\n" : '');
    }

    private function resolveDialect(string $dbType): SqlDialectInterface
    {
        return match ($dbType) {
            DbType::POSTGRESQL->value => new PostgresqlDialect,
            DbType::SQLITE->value => new SqliteDialect,
            DbType::ORACLE->value => new OracleDialect,
            DbType::SQLSERVER->value => new SqlServerDialect,
            DbType::MSACCESS->value => new MsAccessDialect,
            default => new MysqlDialect,
        };
    }

    // Time: O(N), Memory: O(N) — where N = total schema items (tables + rows + connections)
    /**
     * @param  list<array<string, mixed>>  $valueTypes
     * @return array{
     *     format: string,
     *     version: int,
     *     diagram: array{
     *         name: string|null,
     *         dbType: string|null,
     *         schema: list<mixed>,
     *         valueTypes: list<array<string, mixed>>
     *     }
     * }
     */
    public function createJson(
        string $schema,
        array $valueTypes = [],
        ?string $dbType = null,
        ?string $name = null
    ): array
    {
        $decodedSchema = json_decode($schema, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidSchemaException::malformedJson();
        }
        if (! is_array($decodedSchema)) {
            throw InvalidSchemaException::notAnArray();
        }

        return [
            'format' => self::BACKUP_FORMAT,
            'version' => self::BACKUP_VERSION,
            'diagram' => [
                'name' => $name,
                'dbType' => $dbType,
                'schema' => $decodedSchema,
                'valueTypes' => $valueTypes,
            ],
        ];
    }

    // Time: O(N), Memory: O(N) — where N = total schema items (tables + rows + connections)
    public function createSchema(string $script, string $dbType = 'mysql'): string
    {
        return json_encode($this->createSchemaArray($script, $dbType), JSON_PRETTY_PRINT);
    }

    /** @return list<array<string, mixed>> */
    public function createSchemaArray(string $script, string $dbType = 'mysql'): array
    {
        if ($dbType === DbType::ONTOLOGY->value) {
            $ontology = $this->decodeOntologyExport($script);
            if ($ontology !== null) {
                return $this->createSchemaFromOntologyExport(
                    $ontology,
                    $this->parseOntologyValueTypes($ontology)['references']
                );
            }
        }

        $tables = [];
        $rows = [];
        $connections = [];
        $tableX = 50;
        $pendingFks = [];
        $layoutRelationships = [];
        $tableIdByName = [];
        $tableColorById = [];
        $rowIndex = [];
        $enumTypes = []; // type_name (lowercase) => "ENUM('val1','val2')"

        foreach ($this->parseStatements($script) as $statement) {
            // Parse PostgreSQL-style: CREATE TYPE name AS ENUM ('val1', 'val2')
            if (preg_match('/CREATE\s+TYPE\s+["`]?(\w+)["`]?\s+AS\s+ENUM\s*\(([^)]+)\)/is', $statement, $em)) {
                $enumTypes[strtolower($em[1])] = "ENUM({$em[2]})";

                continue;
            }
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`]?(\w+)["`]?\s*\((.*)\)/is', $statement, $m)) {
                $tableX += 400;
                $tableId = uniqid();
                $node = $this->buildTableNode($tableId, $m[1], $tableX);
                $tables[] = $node;
                $tableIdByName[$m[1]] = $tableId;
                $tableColorById[$tableId] = $node['data']['color'];

                $parsed = $this->parseColumns($m[2], $tableId, $tableX, 50, $enumTypes, $dbType);
                array_push($rows, ...$parsed['rows']);
                foreach ($parsed['rows'] as $row) {
                    $rowIndex[$tableId][$row['label']] = $row;
                }

                if (! empty($parsed['uniqueTogether'])) {
                    $tables[count($tables) - 1]['data']['uniqueTogether'] = $parsed['uniqueTogether'];
                }
                if (! empty($parsed['fulltextIndexes'])) {
                    $tables[count($tables) - 1]['data']['fulltextIndexes'] = $parsed['fulltextIndexes'];
                }
                foreach ($this->parseInlineForeignKeys($m[2]) as $fk) {
                    $pendingFks[] = ['sourceTable' => $m[1], 'sourceCol' => $fk['sourceCol'], 'targetTable' => $fk['targetTable'], 'targetCol' => $fk['targetCol']];
                }
            } elseif (preg_match('/ALTER\s+TABLE\s+["`]?(\w+)["`]?\s+ADD\s+(?:CONSTRAINT\s+["`]?\w+["`]?\s+)?FOREIGN\s+KEY\s*\(["`]?(\w+)["`]?\)\s+REFERENCES\s+["`]?(\w+)["`]?\s*\(["`]?(\w+)["`]?\)/i', $statement, $m)) {
                $pendingFks[] = ['sourceTable' => $m[1], 'sourceCol' => $m[2], 'targetTable' => $m[3], 'targetCol' => $m[4]];
            }
        }

        foreach ($pendingFks as $fk) {
            $connection = $this->resolveConnection($tableIdByName, $tableColorById, $rowIndex, $fk['sourceTable'], $fk['sourceCol'], $fk['targetTable'], $fk['targetCol']);
            if ($connection) {
                $connections[] = $connection;
                $layoutRelationships[] = [
                    'source' => $tableIdByName[$fk['targetTable']],
                    'target' => $tableIdByName[$fk['sourceTable']],
                ];
            }
        }

        $tables = $this->layoutService->layout($tables, $rows, $layoutRelationships);

        return array_merge($tables, $rows, $connections);
    }

    /**
     * @return array{
     *     schema: list<array<string, mixed>>,
     *     value_types: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     db_type: DbType|null
     * }
     */
    public function createImportPayload(string $script, string $dbType = 'mysql'): array
    {
        $backup = $this->decodeBackup($script);
        if ($backup !== null) {
            return $backup;
        }

        if ($dbType !== DbType::ONTOLOGY->value) {
            return [
                'schema' => $this->createSchemaArray($script, $dbType),
                'value_types' => [],
                'warnings' => [],
                'db_type' => null,
            ];
        }

        $ontology = $this->decodeOntologyExport($script);
        if ($ontology === null) {
            return [
                'schema' => $this->createSchemaArray($script, $dbType),
                'value_types' => [],
                'warnings' => [],
                'db_type' => null,
            ];
        }

        $parsed = $this->parseOntologyValueTypes($ontology);

        return [
            'schema' => $this->createSchemaFromOntologyExport($ontology, $parsed['references']),
            'value_types' => $parsed['definitions'],
            'warnings' => $parsed['warnings'],
            'db_type' => null,
        ];
    }

    /**
     * @return array{
     *     schema: list<mixed>,
     *     value_types: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     db_type: DbType|null
     * }|null
     */
    private function decodeBackup(string $script): ?array
    {
        $decoded = json_decode($script, true);
        if (! is_array($decoded) || ($decoded['format'] ?? null) !== self::BACKUP_FORMAT) {
            return null;
        }
        if (($decoded['version'] ?? null) !== self::BACKUP_VERSION) {
            throw new InvalidSchemaException('Unsupported OntoloSQL Designer backup version.');
        }

        $diagram = $decoded['diagram'] ?? null;
        if (! is_array($diagram) || ! is_array($diagram['schema'] ?? null)) {
            throw new InvalidSchemaException('Backup does not contain a valid diagram schema.');
        }

        $valueTypes = $diagram['valueTypes'] ?? [];
        if (! is_array($valueTypes)) {
            throw new InvalidSchemaException('Backup does not contain valid value type definitions.');
        }

        $backupDbType = is_string($diagram['dbType'] ?? null)
            ? DbType::tryFrom($diagram['dbType'])
            : null;
        if (($diagram['dbType'] ?? null) !== null && $backupDbType === null) {
            throw new InvalidSchemaException('Backup contains an unsupported diagram type.');
        }

        return [
            'schema' => array_values($diagram['schema']),
            'value_types' => array_values($valueTypes),
            'warnings' => [],
            'db_type' => $backupDbType,
        ];
    }

    // Time: O(N), Memory: O(N) — where N = total schema items (tables + rows + connections)
    /** @return list<array{filename: string, content: string}> */
    public function createMigration(string $schema): array
    {
        [$tables, $rows, $connections] = $this->parseSchemaItems($schema);
        $rowsById = $rows->keyBy('id');
        $tablesById = $tables->keyBy('id');
        $rowsByTable = $rows->groupBy('table_id');
        $connsByTargetTable = $connections->groupBy(fn (array $conn): string => $rowsById->get($conn['target_id'])['table_id'] ?? '');
        $files = [];

        foreach ($tables as $index => $table) {
            $colLines = $rowsByTable->get($table['id'], collect())
                ->map(fn ($row) => $this->buildLaravelColumn($row))->filter()->values()->all();

            $fkLines = $connsByTargetTable->get($table['id'], collect())
                ->map(function ($conn) use ($rowsById, $tablesById) {
                    $sourceRow = $rowsById->get($conn['source_id']);
                    $targetRow = $rowsById->get($conn['target_id']);
                    if (! $sourceRow || ! $targetRow) {
                        return null;
                    }
                    $sourceTable = $tablesById->get($sourceRow['table_id'])['name'];

                    return "            \$table->foreign('{$targetRow['name']}')->references('{$sourceRow['name']}')->on('{$sourceTable}');";
                })->filter()->values()->all();

            $body = implode("\n", array_merge($colLines, $fkLines));
            $pad = str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
            $filename = "2025_01_01_{$pad}_create_{$table['name']}_table.php";

            $files[] = ['filename' => $filename, 'content' => $this->buildMigrationFileContent($table['name'], $body)];
        }

        return $files;
    }

    // --- Private helpers ---

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>, 2: Collection<int, array<string, mixed>>}
     */
    private function parseSchemaItems(string $schema): array
    {
        if (trim($schema) === '') {
            throw InvalidSchemaException::emptySchema();
        }

        $decoded = json_decode($schema, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidSchemaException::malformedJson();
        }

        if (! is_array($decoded)) {
            throw InvalidSchemaException::notAnArray();
        }

        $tables = collect();
        $rows = collect();
        $connections = collect();

        foreach ($decoded as $item) {
            match ($item['type'] ?? null) {
                'table' => $tables->push([
                    'id' => $item['id'],
                    'name' => $item['label'],
                    'note' => trim((string) ($item['data']['description'] ?? $item['data']['note'] ?? '')),
                    'unique_together' => $item['data']['uniqueTogether'] ?? [],
                    'fulltext_indexes' => $item['data']['fulltextIndexes'] ?? [],
                ]),
                'row' => $rows->push([
                    'id' => $item['id'],
                    'name' => $item['label'],
                    'table_id' => $item['parentNode'],
                    'key_mod' => match ($item['data']['keyMod'] ?? null) {
                        null, 'None' => null, default => $item['data']['keyMod']
                    },
                    'sql_type' => $item['data']['sqlType'] ?? 'VARCHAR(255)',
                    'nullable' => $item['data']['nullable'] ?? false,
                    'unsigned' => $item['data']['unsigned'] ?? false,
                    'default_value' => $item['data']['defaultValue'] ?? null,
                    'comment' => $item['data']['description'] ?? $item['data']['comment'] ?? null,
                    'value_type_id' => is_string($item['data']['valueTypeId'] ?? null)
                        ? $item['data']['valueTypeId']
                        : null,
                ]),
                default => isset($item['sourceNode']['id'], $item['targetNode']['id'])
                    ? $connections->push(['source_id' => $item['sourceNode']['id'], 'target_id' => $item['targetNode']['id']])
                    : null,
            };
        }

        return [$tables, $rows, $connections];
    }

    /** @return array<int, string> */
    private function parseStatements(string $sql): array
    {
        return array_filter(array_map('trim', explode(';', $sql)));
    }

    /** @return array<string, mixed> */
    private function buildTableNode(string $id, string $name, int $x, int $y = 50): array
    {
        return [
            'id' => $id,
            'type' => 'table',
            'position' => ['x' => $x, 'y' => $y],
            'data' => ['toolbarPosition' => 'top', 'toolbarVisible' => true, 'color' => '#3d7a5c', 'uniqueTogether' => [], 'fulltextIndexes' => [], 'description' => '', 'ontologyActions' => ['create' => false, 'modify' => false, 'delete' => false]],
            'label' => $name,
            'style' => [
                'display' => 'flex', 'border' => '1px solid #3d7a5c',
                'background' => '#3d7a5c', 'borderColor' => '#3d7a5c', 'color' => 'white',
                'width' => '350px', 'height' => '40px',
                'alignItems' => 'center', 'justifyContent' => 'space-between',
                'borderRadius' => '6px 6px 0 0',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildRowNode(string $id, string $tableId, string $name, int $x, int $y, int $index, array $data): array
    {
        return [
            'id' => $id,
            'type' => 'row',
            'draggable' => false,
            'position' => ['x' => 0, 'y' => 40 + ($index * 40)],
            'data' => $data,
            'label' => $name,
            'style' => [
                'display' => 'flex', 'border' => '1px solid #898989',
                'borderColor' => '#898989', 'background' => '#ffffff', 'color' => '#000000',
                'width' => '350px', 'height' => '40px',
                'alignItems' => 'center', 'justifyContent' => 'space-between',
            ],
            'parentNode' => $tableId,
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeOntologyExport(string $script): ?array
    {
        $firstContentOffset = strspn($script, " \n\r\t");
        if ($firstContentOffset === strlen($script) || $script[$firstContentOffset] !== '{') {
            return null;
        }

        $decoded = json_decode($script, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidSchemaException::malformedJson();
        }
        if (! is_array($decoded) || ! isset($decoded['objectTypes']) || ! is_array($decoded['objectTypes'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $ontology
     * @param  array<string, string>  $valueTypeReferences
     * @return list<array<string, mixed>>
     */
    private function createSchemaFromOntologyExport(array $ontology, array $valueTypeReferences = []): array
    {
        $tables = [];
        $rows = [];
        $connections = [];
        $tablesByRid = [];
        $rowsByPropertyRid = [];
        $rowsByObjectAndPropertyId = [];
        $primaryRowsByObjectRid = [];
        $layoutRelationships = [];

        foreach ($ontology['objectTypes'] as $objectIndex => $objectType) {
            if (! is_array($objectType)) {
                continue;
            }

            $x = 50;
            $y = 50;
            $tableId = 'ontology-table-'.($objectType['rid'] ?? $objectType['id'] ?? $objectIndex);
            $displayMetadata = is_array($objectType['displayMetadata'] ?? null) ? $objectType['displayMetadata'] : [];
            $name = (string) ($displayMetadata['displayName'] ?? $objectType['apiName'] ?? $objectType['id'] ?? 'Object');
            $table = $this->buildTableNode($tableId, $name, $x, $y);
            $table['data']['description'] = trim((string) ($displayMetadata['description'] ?? ''));
            $table['data']['ontologyMetadata'] = [
                'id' => $objectType['id'] ?? null,
                'rid' => $objectType['rid'] ?? null,
                'apiName' => $objectType['apiName'] ?? null,
                'titlePropertyId' => $objectType['titlePropertyId'] ?? null,
            ];
            $tables[] = $table;
            $tableIndex = array_key_last($tables);

            $objectRid = (string) ($objectType['rid'] ?? '');
            if ($objectRid !== '') {
                $tablesByRid[$objectRid] = $table;
            }

            $primaryKeys = array_map('strval', is_array($objectType['primaryKeys'] ?? null) ? $objectType['primaryKeys'] : []);
            $properties = is_array($objectType['properties'] ?? null) ? $objectType['properties'] : [];
            foreach ($properties as $propertyIndex => $property) {
                if (! is_array($property)) {
                    continue;
                }

                $propertyId = (string) ($property['id'] ?? $property['apiName'] ?? "property-{$propertyIndex}");
                $propertyRid = (string) ($property['rid'] ?? '');
                $rowId = 'ontology-row-'.($propertyRid !== '' ? $propertyRid : "{$tableId}-{$propertyId}");
                $propertyDisplay = is_array($property['displayMetadata'] ?? null) ? $property['displayMetadata'] : [];
                $baseType = is_array($property['baseType'] ?? null) ? $property['baseType'] : ['type' => 'STRING'];
                $canvasType = $this->ontologyCanvasType($baseType);
                $isPrimary = in_array($propertyId, $primaryKeys, true)
                    || in_array((string) ($property['apiName'] ?? ''), $primaryKeys, true);
                $nullability = $property['dataConstraints']['nullability'] ?? null;
                $row = $this->buildRowNode($rowId, $tableId, $propertyId, $x, $y + 40 + ($propertyIndex * 40), $propertyIndex, [
                    'editing' => false,
                    'showModal' => false,
                    'showOptionsModal' => false,
                    'keyMod' => $isPrimary ? 'PRIMARY KEY' : 'None',
                    'sqlType' => $canvasType,
                    'nullable' => ! $isPrimary && $nullability !== 'NO_NULLS',
                    'unsigned' => false,
                    'defaultValue' => '',
                    'description' => trim((string) ($propertyDisplay['description'] ?? '')),
                    'indexed' => (bool) ($property['indexedForSearch'] ?? false),
                    'ontologyBaseType' => $baseType,
                    'ontologyImportedSqlType' => $canvasType,
                    'ontologyMetadata' => [
                        'rid' => $property['rid'] ?? null,
                        'apiName' => $property['apiName'] ?? null,
                        'displayName' => $propertyDisplay['displayName'] ?? null,
                        'source' => $property['source'] ?? null,
                    ],
                ]);
                $valueTypeId = $this->resolveOntologyValueTypeId($property, $valueTypeReferences);
                if ($valueTypeId !== null) {
                    $row['data']['valueTypeId'] = $valueTypeId;
                }
                $rows[] = $row;

                if ($propertyRid !== '') {
                    $rowsByPropertyRid[$propertyRid] = $row;
                }
                if ($objectRid !== '') {
                    $rowsByObjectAndPropertyId[$objectRid][$propertyId] = $row;
                    if ($isPrimary) {
                        $primaryRowsByObjectRid[$objectRid][] = $row;
                    }
                }
            }

            $titlePropertyId = (string) ($objectType['titlePropertyId'] ?? '');
            if ($titlePropertyId !== '') {
                foreach ($rows as $row) {
                    if (($row['parentNode'] ?? null) !== $tableId) {
                        continue;
                    }
                    $metadata = is_array($row['data']['ontologyMetadata'] ?? null)
                        ? $row['data']['ontologyMetadata']
                        : [];
                    if (in_array($titlePropertyId, [
                        (string) ($row['id'] ?? ''),
                        (string) ($row['label'] ?? ''),
                        (string) ($metadata['rid'] ?? ''),
                        (string) ($metadata['apiName'] ?? ''),
                    ], true)) {
                        $tables[$tableIndex]['data']['titlePropertyRowId'] = $row['id'];
                        break;
                    }
                }
            }

        }

        foreach (($ontology['relations'] ?? []) as $relationIndex => $relation) {
            if (! is_array($relation)) {
                continue;
            }
            $definition = is_array($relation['definition'] ?? null) ? $relation['definition'] : [];
            $type = $definition['type'] ?? null;

            if ($type === 'oneToMany' && is_array($definition['oneToMany'] ?? null)) {
                $link = $definition['oneToMany'];
                $oneRid = (string) ($link['objectTypeRidOneSide'] ?? '');
                $manyRid = (string) ($link['objectTypeRidManySide'] ?? '');
                $mapping = is_array($link['oneSidePrimaryKeyToManySidePropertyMapping'] ?? null)
                    ? $link['oneSidePrimaryKeyToManySidePropertyMapping']
                    : [];
                $sourceRow = null;
                $targetRow = null;
                foreach ($mapping as $sourcePropertyRid => $targetPropertyRid) {
                    $sourceRow = $rowsByPropertyRid[(string) $sourcePropertyRid] ?? null;
                    $targetRow = $rowsByPropertyRid[(string) $targetPropertyRid] ?? null;
                    if ($sourceRow && $targetRow) {
                        break;
                    }
                }
                $sourceRow ??= $primaryRowsByObjectRid[$oneRid][0] ?? null;
                $foreignPropertyId = (string) ($link['manySideForeignKeyPropertyId'] ?? '');
                $targetRow ??= $rowsByObjectAndPropertyId[$manyRid][$foreignPropertyId] ?? null;

                if ($sourceRow && $targetRow) {
                    if (($targetRow['data']['keyMod'] ?? null) !== 'PRIMARY KEY') {
                        $this->markRowAsForeignKey($rows, $targetRow['id']);
                    }
                    $relationshipType = ($link['cardinalityHint'] ?? null) === 'ONE_TO_ONE' ? 'one-to-one' : 'one-to-many';
                    $connections[] = $this->buildOntologyConnection(
                        (string) ($relation['rid'] ?? "relation-{$relationIndex}"),
                        $sourceRow,
                        $targetRow,
                        $tablesByRid[$manyRid]['data']['color'] ?? '#3d7a5c',
                        $relationshipType
                    );
                    if ($oneRid !== '' && $manyRid !== '' && isset($tablesByRid[$oneRid], $tablesByRid[$manyRid])) {
                        $layoutRelationships[] = [
                            'source' => $tablesByRid[$oneRid]['id'],
                            'target' => $tablesByRid[$manyRid]['id'],
                        ];
                    }
                }
            } elseif ($type === 'manyToMany' && is_array($definition['manyToMany'] ?? null)) {
                $link = $definition['manyToMany'];
                $aRid = (string) ($link['objectTypeRidA'] ?? '');
                $bRid = (string) ($link['objectTypeRidB'] ?? '');
                $sourceRow = $primaryRowsByObjectRid[$aRid][0] ?? null;
                $targetRow = $primaryRowsByObjectRid[$bRid][0] ?? null;
                if ($sourceRow && $targetRow) {
                    $connections[] = $this->buildOntologyConnection(
                        (string) ($relation['rid'] ?? "relation-{$relationIndex}"),
                        $sourceRow,
                        $targetRow,
                        $tablesByRid[$bRid]['data']['color'] ?? '#3d7a5c',
                        'many-to-many'
                    );
                    if ($aRid !== '' && $bRid !== '' && isset($tablesByRid[$aRid], $tablesByRid[$bRid])) {
                        $layoutRelationships[] = [
                            'source' => $tablesByRid[$aRid]['id'],
                            'target' => $tablesByRid[$bRid]['id'],
                        ];
                    }
                }
            }
        }

        $tables = $this->layoutService->layout($tables, $rows, $layoutRelationships);

        return array_merge($tables, $rows, $connections);
    }

    /**
     * @param array<string, mixed> $ontology
     * @return array{
     *     definitions: list<array<string, mixed>>,
     *     references: array<string, string>,
     *     warnings: list<string>
     * }
     */
    private function parseOntologyValueTypes(array $ontology): array
    {
        $rawValueTypes = $ontology['valueTypes'] ?? [];
        if (! is_array($rawValueTypes)) {
            return ['definitions' => [], 'references' => [], 'warnings' => []];
        }
        if (is_array($rawValueTypes['valueTypes'] ?? null)) {
            $rawValueTypes = $rawValueTypes['valueTypes'];
        }

        $definitions = [];
        $references = [];
        $warnings = [];

        foreach ($rawValueTypes as $key => $rawDefinition) {
            if (! is_array($rawDefinition)) {
                continue;
            }

            $apiName = (string) ($rawDefinition['apiName'] ?? (is_string($key) ? $key : ''));
            if ($apiName === '') {
                continue;
            }
            $idSource = (string) ($rawDefinition['rid'] ?? $rawDefinition['id'] ?? $apiName);
            $id = 'ontology-value-type-'.substr(hash('sha256', $idSource), 0, 24);
            $displayMetadata = is_array($rawDefinition['displayMetadata'] ?? null)
                ? $rawDefinition['displayMetadata']
                : [];
            $baseType = $this->normalizeOntologyValueTypeBase(
                is_array($rawDefinition['baseType'] ?? null)
                    ? $rawDefinition['baseType']
                    : (is_array($rawDefinition['type']['type'] ?? null)
                        ? $rawDefinition['type']['type']
                        : ['type' => $rawDefinition['type']['type'] ?? $rawDefinition['type'] ?? 'string'])
            );
            if ($baseType === null) {
                $warnings[] = "Skipped value type {$apiName}: unsupported base type.";

                continue;
            }

            $constraints = [];
            foreach (($rawDefinition['constraints'] ?? $rawDefinition['type']['constraints'] ?? []) as $rawConstraint) {
                if (! is_array($rawConstraint)) {
                    continue;
                }
                $constraint = $this->normalizeOntologyValueTypeConstraint($rawConstraint);
                if ($constraint === null) {
                    $warnings[] = "Skipped an unsupported constraint on value type {$apiName}.";
                    continue;
                }
                if ($baseType['type'] !== 'string') {
                    $warnings[] = "Skipped a constraint on non-string value type {$apiName}.";
                    continue;
                }
                $constraints[] = $constraint;
            }

            $definition = [
                'id' => $id,
                'apiName' => $apiName,
                'displayName' => (string) ($displayMetadata['displayName'] ?? $rawDefinition['displayName'] ?? $apiName),
                'description' => (string) ($displayMetadata['description'] ?? $rawDefinition['description'] ?? ''),
                'version' => (string) ($rawDefinition['version'] ?? '1.0.0'),
                'baseType' => $baseType,
                'constraints' => $constraints,
            ];
            $definitions[] = $definition;

            foreach ([$rawDefinition['rid'] ?? null, $rawDefinition['id'] ?? null, $apiName, $key] as $reference) {
                if (is_string($reference) && $reference !== '') {
                    $references[$reference] = $id;
                }
            }
        }

        return compact('definitions', 'references', 'warnings');
    }

    /**
     * @param array<string, mixed> $baseType
     * @return array<string, mixed>|null
     */
    private function normalizeOntologyValueTypeBase(array $baseType): ?array
    {
        $type = strtolower((string) ($baseType['type'] ?? 'string'));
        $type = $type === 'structv2' ? 'struct' : $type;
        $allowedSimple = ['boolean', 'date', 'decimal', 'double', 'float', 'integer', 'long', 'short', 'string', 'timestamp'];

        if (in_array($type, $allowedSimple, true)) {
            return ['type' => $type];
        }

        if ($type === 'array') {
            $array = is_array($baseType['array'] ?? null) ? $baseType['array'] : $baseType;
            $element = $array['elementType'] ?? $array['subType'] ?? 'string';
            $elementType = is_array($element)
                ? strtolower((string) ($element['type'] ?? 'string'))
                : strtolower((string) $element);

            return in_array($elementType, $allowedSimple, true)
                ? ['type' => 'array', 'elementType' => $elementType]
                : null;
        }

        if ($type !== 'struct') {
            return null;
        }

        $struct = is_array($baseType['structV2'] ?? null)
            ? $baseType['structV2']
            : (is_array($baseType['struct'] ?? null) ? $baseType['struct'] : $baseType);
        $rawFields = $struct['fields'] ?? $struct['structFields'] ?? [];
        $fields = [];
        foreach ($rawFields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $fieldType = $field['baseType'] ?? $field['fieldType'] ?? $field['type'] ?? 'string';
            $fieldType = is_array($fieldType)
                ? strtolower((string) ($fieldType['type'] ?? 'string'))
                : strtolower((string) $fieldType);
            if (! in_array($fieldType, $allowedSimple, true)) {
                continue;
            }
            $apiName = (string) ($field['identifier'] ?? $field['apiName'] ?? "field{$index}");
            $fields[] = [
                'id' => 'ontology-struct-field-'.substr(hash('sha256', $apiName.'-'.$index), 0, 16),
                'apiName' => $apiName,
                'type' => $fieldType,
            ];
        }

        return $fields === [] ? null : ['type' => 'struct', 'fields' => $fields];
    }

    /**
     * @param array<string, mixed> $rawConstraint
     * @return array<string, mixed>|null
     */
    private function normalizeOntologyValueTypeConstraint(array $rawConstraint): ?array
    {
        $failure = $rawConstraint['failureMessage']
            ?? $rawConstraint['constraint']['failureMessage']
            ?? $rawConstraint['constraint']['constraint']['failureMessage']
            ?? null;
        $failureMessage = is_array($failure) ? (string) ($failure['message'] ?? '') : (string) ($failure ?? '');

        $constraint = $rawConstraint['constraint']['constraint']
            ?? $rawConstraint['constraint']
            ?? $rawConstraint;
        if (! is_array($constraint)) {
            return null;
        }
        if (($constraint['type'] ?? null) === 'string' && is_array($constraint['string'] ?? null)) {
            $constraint = $constraint['string'];
        }

        $id = 'ontology-constraint-'.substr(hash('sha256', json_encode($rawConstraint)), 0, 16);
        $type = $constraint['type'] ?? null;
        if ($type === 'regex' || isset($constraint['regex'])) {
            $regex = is_array($constraint['regex'] ?? null) ? $constraint['regex'] : $constraint;

            return [
                'id' => $id,
                'type' => 'regex',
                'regexPattern' => (string) ($regex['regexPattern'] ?? $regex['regex'] ?? ''),
                'usePartialMatch' => (bool) ($regex['usePartialMatch'] ?? false),
                'failureMessage' => $failureMessage,
            ];
        }
        if ($type === 'isRid' || isset($constraint['isRid'])) {
            return ['id' => $id, 'type' => 'isRid', 'failureMessage' => $failureMessage];
        }
        if ($type === 'isUuid' || isset($constraint['isUuid'])) {
            return ['id' => $id, 'type' => 'isUuid', 'failureMessage' => $failureMessage];
        }
        if ($type === 'length' || isset($constraint['length'])) {
            $length = is_array($constraint['length'] ?? null) ? $constraint['length'] : $constraint;
            $normalized = ['id' => $id, 'type' => 'length', 'failureMessage' => $failureMessage];
            if (isset($length['minSize'])) {
                $normalized['minSize'] = (int) $length['minSize'];
            }
            if (isset($length['maxSize'])) {
                $normalized['maxSize'] = (int) $length['maxSize'];
            }

            return isset($normalized['minSize']) || isset($normalized['maxSize']) ? $normalized : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, string> $references
     */
    private function resolveOntologyValueTypeId(array $property, array $references): ?string
    {
        $candidates = [
            $property['valueTypeRid'] ?? null,
            $property['valueTypeId'] ?? null,
            $property['valueType'] ?? null,
            $property['valueTypeReference'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && isset($references[$candidate])) {
                return $references[$candidate];
            }
            if (is_array($candidate)) {
                foreach (['rid', 'id', 'apiName'] as $key) {
                    $reference = $candidate[$key] ?? null;
                    if (is_string($reference) && isset($references[$reference])) {
                        return $references[$reference];
                    }
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $baseType */
    private function ontologyCanvasType(array $baseType): string
    {
        $type = strtoupper((string) ($baseType['type'] ?? 'STRING'));

        return match ($type) {
            'ARRAY' => 'ARRAY<'.$this->ontologyCanvasType(
                is_array($baseType['subType'] ?? null) ? $baseType['subType'] : ['type' => 'STRING']
            ).'>',
            'VECTOR' => isset($baseType['dimension']) ? 'VECTOR('.(int) $baseType['dimension'].')' : 'VECTOR',
            'DECIMAL' => isset($baseType['precision'], $baseType['scale'])
                ? 'DECIMAL('.(int) $baseType['precision'].','.(int) $baseType['scale'].')'
                : 'DECIMAL(10,2)',
            'MEDIA_REFERENCE' => 'MEDIAREFERENCE',
            'TIME_DEPENDENT' => 'GEOTIMESERIES',
            default => $type,
        };
    }

    /** @param list<array<string, mixed>> $rows */
    private function markRowAsForeignKey(array &$rows, string $rowId): void
    {
        foreach ($rows as &$row) {
            if (($row['id'] ?? null) === $rowId) {
                $row['data']['keyMod'] = 'FOREIGN KEY';

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @param  array<string, mixed>  $targetRow
     * @return array<string, mixed>
     */
    private function buildOntologyConnection(string $id, array $sourceRow, array $targetRow, string $color, string $relationshipType): array
    {
        $markers = match ($relationshipType) {
            'one-to-one' => ['none', 'none'],
            'many-to-many' => ['url(#chickenFoot)', 'url(#chickenFoot)'],
            default => ['url(#chickenFoot)', 'none'],
        };

        return [
            'id' => 'ontology-edge-'.$id,
            'type' => 'chickenFoot',
            'source' => $sourceRow['id'],
            'target' => $targetRow['id'],
            'sourceHandle' => 'source-right',
            'targetHandle' => 'target-left',
            'updatable' => true,
            'style' => ['stroke' => $color],
            'data' => [
                'relationshipType' => $relationshipType,
                'markerStart' => $markers[0],
                'markerEnd' => $markers[1],
                'color' => $color,
            ],
        ];
    }

    /** @return list<string> */
    private function splitColumnDefinitions(string $content): array
    {
        $lines = [];
        $current = '';
        $depth = 0;

        foreach (str_split(preg_replace('/\s+/', ' ', trim($content))) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $lines[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $lines[] = trim($current);
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $enumTypes  lowercase type_name => "ENUM('val1','val2')" (from CREATE TYPE statements)
     * @return array{rows: list<array<string, mixed>>, uniqueTogether: list<list<string>>, fulltextIndexes: list<list<string>>}
     */
    private function parseColumns(string $tableContent, string $tableId, int $tableX, int $tableY = 50, array $enumTypes = [], string $dbType = 'mysql'): array
    {
        $lines = $this->splitColumnDefinitions($tableContent);
        $constraints = [];
        $uniqueTogetherConstraints = [];
        $fulltextIndexConstraints = [];
        $usedNames = [];
        $rows = [];
        $index = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(?:CONSTRAINT\s+["`]?\w+["`]?\s+)?PRIMARY\s+KEY\s*\(\s*["`]?(\w+)["`]?\s*\)/i', $line, $m)) {
                $constraints[$m[1]] = 'PRIMARY KEY';
            } elseif (preg_match('/^(?:CONSTRAINT\s+["`]?\w+["`]?\s+)?UNIQUE(?:\s+KEY(?:\s+["`]?\w+["`]?)?)?\s*\(\s*([^)]+)\s*\)/i', $line, $m)) {
                $cols = array_values(array_filter(array_map(
                    fn ($c) => trim(str_replace(['`', '"'], '', $c)),
                    explode(',', $m[1])
                )));
                if (count($cols) === 1) {
                    $constraints[$cols[0]] = 'UNIQUE';
                } elseif (count($cols) >= 2) {
                    $uniqueTogetherConstraints[] = $cols;
                }
            } elseif (preg_match('/^FULLTEXT\s+(?:KEY|INDEX)\s+["`]?\w+["`]?\s*\(\s*([^)]+)\s*\)/i', $line, $m)) {
                $cols = array_values(array_filter(array_map(
                    fn ($c) => trim(str_replace(['`', '"'], '', $c)),
                    explode(',', $m[1])
                )));
                if (count($cols) >= 1) {
                    $fulltextIndexConstraints[] = $cols;
                }
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/^(?:CONSTRAINT\s|PRIMARY\s+KEY|UNIQUE\s+(?:KEY|INDEX)|UNIQUE\s*\(|FOREIGN\s+KEY|KEY\s|INDEX\s|FULLTEXT\s|CHECK\s*\()/i', $line)) {
                continue;
            }
            if (! preg_match('/^["`]?(\w+)["`]?\s+["`]?([a-zA-Z_]\w*)["`]?(?:\(([^)]+)\))?(?:\s+(UNSIGNED))?(?:\s+(NOT\s+NULL|NULL))?(?:\s+(PRIMARY\s+KEY|UNIQUE))?(?:\s+DEFAULT\s+\'([^\']*)\')?(?:\s+COMMENT\s+\'([^\']*)\')?/i', $line, $m)) {
                continue;
            }
            if (strtoupper($m[2]) === 'SET' && $dbType !== DbType::ONTOLOGY->value) {
                $m[2] = 'VARCHAR';
                $m[3] = '255';
            }

            $baseName = $m[1];
            $name = $baseName;
            $counter = 1;
            while (in_array($name, $usedNames)) {
                $name = $baseName.'_'.$counter++;
            }
            $usedNames[] = $name;

            // Resolve enum types declared via CREATE TYPE ... AS ENUM (PostgreSQL import)
            $resolvedEnumType = $enumTypes[strtolower($m[2])] ?? null;
            $sqlType = $resolvedEnumType ?? strtoupper($m[2]).(isset($m[3]) && $m[3] !== '' ? "($m[3])" : '');
            if ($dbType === DbType::ONTOLOGY->value && str_starts_with(strtoupper($sqlType), 'SET(')) {
                $sqlType = 'ENUM'.substr($sqlType, 3);
            }
            $sqlType = $this->normalizeImportedSqlType($sqlType, $dbType);
            $unsigned = isset($m[4]) && strtoupper($m[4]) === 'UNSIGNED';
            $nullable = isset($m[5]) ? strtoupper($m[5]) === 'NULL' : true;
            $keyMod = isset($m[6]) ? strtoupper(preg_replace('/\s+/', ' ', $m[6])) : ($constraints[$baseName] ?? null);
            $defaultValue = $m[7] ?? '';
            $comment = $m[8] ?? '';

            $rowId = uniqid();
            $rows[] = $this->buildRowNode($rowId, $tableId, $name, $tableX, $tableY + 40 + ($index * 40), $index, [
                'editing' => false, 'showModal' => false, 'showOptionsModal' => false,
                'keyMod' => $keyMod ?? 'None',
                'sqlType' => $sqlType, 'nullable' => $nullable, 'unsigned' => $unsigned,
                'defaultValue' => $defaultValue, 'description' => $comment, 'indexed' => true,
            ]);
            $index++;
        }

        return ['rows' => $rows, 'uniqueTogether' => $uniqueTogetherConstraints, 'fulltextIndexes' => $fulltextIndexConstraints];
    }

    private function normalizeImportedSqlType(string $sqlType, string $dbType): string
    {
        if ($dbType !== DbType::ONTOLOGY->value) {
            return $sqlType;
        }

        $type = strtolower(trim($sqlType));
        $base = preg_replace('/\s*\(.*/', '', $type);

        if (str_starts_with($base, 'enum')) {
            return $sqlType;
        }
        if ($type === 'tinyint(1)' || in_array($base, ['bool', 'boolean', 'yesno', 'bit'], true)) {
            return 'BOOLEAN';
        }
        if (in_array($base, ['tinyint', 'byte'], true)) {
            return 'BYTE';
        }
        if (in_array($base, ['smallint', 'int2', 'short', 'smallserial'], true)) {
            return 'SHORT';
        }
        if (in_array($base, ['mediumint', 'int', 'integer', 'int4', 'serial', 'long', 'autoincrement'], true)) {
            return 'INTEGER';
        }
        if (in_array($base, ['bigint', 'int8', 'bigserial'], true)) {
            return 'LONG';
        }
        if (in_array($base, ['decimal', 'numeric', 'number', 'currency', 'money', 'smallmoney'], true)) {
            return preg_match('/\((\d+)\s*,\s*(\d+)\)/', $type, $matches)
                ? sprintf('DECIMAL(%d,%d)', $matches[1], $matches[2])
                : 'DECIMAL(10,2)';
        }
        if (in_array($base, ['float', 'real', 'single', 'float4', 'binary_float'], true)) {
            return 'FLOAT';
        }
        if (in_array($base, ['double', 'double precision', 'float8', 'binary_double'], true)) {
            return 'DOUBLE';
        }
        if ($base === 'date') {
            return 'DATE';
        }
        if (str_starts_with($base, 'timestamp') || in_array($base, ['datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'], true)) {
            return 'TIMESTAMP';
        }
        if (in_array($base, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'bytea', 'binary', 'varbinary', 'raw', 'oleobject', 'attachment'], true)) {
            return 'ATTACHMENT';
        }
        if (in_array($base, ['geopoint', 'geoshape', 'mediareference', 'geotimeseries'], true)) {
            return strtoupper($base);
        }

        return 'STRING';
    }

    /** @return list<string> */
    private function sqlCommentLines(string $comment, string $subject): array
    {
        $comment = trim($comment);
        if ($comment === '') {
            return [];
        }

        $lines = preg_split('/\R/', $comment) ?: [];
        $result = [];
        foreach ($lines as $index => $line) {
            $line = trim(str_replace('--', '- -', $line));
            $result[] = $index === 0
                ? "-- {$subject}: {$line}"
                : "-- {$line}";
        }

        return $result;
    }

    /** @return list<array{sourceCol: string, targetTable: string, targetCol: string}> */
    private function parseInlineForeignKeys(string $tableContent): array
    {
        $fks = [];
        foreach ($this->splitColumnDefinitions($tableContent) as $line) {
            if (preg_match('/(?:CONSTRAINT\s+["`]?\w+["`]?\s+)?FOREIGN\s+KEY\s*\(["`]?(\w+)["`]?\)\s+REFERENCES\s+["`]?(\w+)["`]?\s*\(["`]?(\w+)["`]?\)/i', $line, $m)) {
                $fks[] = ['sourceCol' => $m[1], 'targetTable' => $m[2], 'targetCol' => $m[3]];
            }
        }

        return $fks;
    }

    private function buildMigrationFileContent(string $tableName, string $body): string
    {
        $t = '$table';

        return "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$tableName}', function (Blueprint {$t}) {\n{$body}\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$tableName}');\n    }\n};\n";
    }

    /** @param array<string, mixed> $col */
    private function buildLaravelColumn(array $col): ?string
    {
        $name = $col['name'];
        $rawType = trim($col['sql_type'] ?? 'VARCHAR(255)');
        $typeUpper = strtoupper($rawType);
        $firstWord = strtoupper(preg_replace('/[\s(].*/', '', $typeUpper));

        if (in_array($firstWord, ['INDEX', 'KEY', 'CONSTRAINT', 'FOREIGN', 'CHECK', 'PRIMARY', 'UNIQUE'])) {
            return null;
        }

        preg_match('/\(([^)]+)\)/', $rawType, $sizeMatch);
        $sizeStr = $sizeMatch[1] ?? null;

        if (preg_match('/^TINYINT\s*\(\s*1\s*\)/i', $rawType)) {
            $method = "boolean('{$name}')";
        } elseif (preg_match('/^TINYINT/i', $typeUpper)) {
            $method = $col['unsigned'] ? "unsignedTinyInteger('{$name}')" : "tinyInteger('{$name}')";
        } elseif (preg_match('/^SMALLINT/i', $typeUpper)) {
            $method = $col['unsigned'] ? "unsignedSmallInteger('{$name}')" : "smallInteger('{$name}')";
        } elseif (preg_match('/^MEDIUMINT/i', $typeUpper)) {
            $method = $col['unsigned'] ? "unsignedMediumInteger('{$name}')" : "mediumInteger('{$name}')";
        } elseif (preg_match('/^BIGINT/i', $typeUpper)) {
            $method = $col['unsigned'] ? "unsignedBigInteger('{$name}')" : "bigInteger('{$name}')";
        } elseif (preg_match('/^INT/i', $typeUpper)) {
            $method = $col['unsigned'] ? "unsignedInteger('{$name}')" : "integer('{$name}')";
        } elseif (preg_match('/^VARCHAR/i', $typeUpper)) {
            $method = ($sizeStr && $sizeStr !== '255') ? "string('{$name}', {$sizeStr})" : "string('{$name}')";
        } elseif (preg_match('/^CHAR/i', $typeUpper)) {
            $method = $sizeStr ? "char('{$name}', {$sizeStr})" : "char('{$name}')";
        } elseif (preg_match('/^LONGTEXT/i', $typeUpper)) {
            $method = "longText('{$name}')";
        } elseif (preg_match('/^MEDIUMTEXT/i', $typeUpper)) {
            $method = "mediumText('{$name}')";
        } elseif (preg_match('/^TINYTEXT/i', $typeUpper)) {
            $method = "tinyText('{$name}')";
        } elseif (preg_match('/^TEXT/i', $typeUpper)) {
            $method = "text('{$name}')";
        } elseif (preg_match('/^DECIMAL/i', $typeUpper)) {
            if ($sizeStr) {
                $parts = array_map('trim', explode(',', $sizeStr, 2));
                [$prec, $scale] = [$parts[0], $parts[1] ?? null];
                $method = $scale ? "decimal('{$name}', {$prec}, {$scale})" : "decimal('{$name}', {$prec})";
            } else {
                $method = "decimal('{$name}')";
            }
        } elseif (preg_match('/^DOUBLE/i', $typeUpper)) {
            $method = "double('{$name}')";
        } elseif (preg_match('/^FLOAT/i', $typeUpper)) {
            $method = "float('{$name}')";
        } elseif (preg_match('/^DATETIME/i', $typeUpper)) {
            $method = "dateTime('{$name}')";
        } elseif (preg_match('/^TIMESTAMP/i', $typeUpper)) {
            $method = "timestamp('{$name}')";
        } elseif (preg_match('/^DATE/i', $typeUpper)) {
            $method = "date('{$name}')";
        } elseif (preg_match('/^TIME/i', $typeUpper)) {
            $method = "time('{$name}')";
        } elseif (preg_match('/^YEAR/i', $typeUpper)) {
            $method = "year('{$name}')";
        } elseif (preg_match('/^BOOL/i', $typeUpper)) {
            $method = "boolean('{$name}')";
        } elseif (preg_match('/^JSON/i', $typeUpper)) {
            $method = "json('{$name}')";
        } elseif (preg_match('/^(BLOB|BINARY|VARBINARY)/i', $typeUpper)) {
            $method = "binary('{$name}')";
        } elseif (preg_match('/^ENUM/i', $typeUpper)) {
            preg_match('/ENUM\s*\((.+)\)/i', $rawType, $enumMatch);
            $method = $enumMatch ? "enum('{$name}', [{$enumMatch[1]}])" : "string('{$name}')";
        } else {
            $method = "string('{$name}')";
        }

        $mods = '';
        if ($col['nullable']) {
            $mods .= '->nullable()';
        }
        if ($col['default_value'] !== null && $col['default_value'] !== '') {
            $dv = $col['default_value'];
            if ($dv === 'NULL') {
                $mods .= '->default(null)';
            } elseif (is_numeric($dv)) {
                $mods .= "->default({$dv})";
            } else {
                $mods .= "->default('".str_replace("'", "\\'", $dv)."')";
            }
        }
        if ($col['key_mod'] === 'PRIMARY KEY') {
            $mods .= '->primary()';
        }
        if ($col['comment']) {
            $mods .= "->comment('".str_replace("'", "\\'", $col['comment'])."')";
        }

        return "            \$table->{$method}{$mods};";
    }

    /**
     * @param  array<string, string>  $tableIdByName
     * @param  array<string, string>  $tableColorById
     * @param  array<string, array<string, array<string, mixed>>>  $rowIndex
     * @return array<string, mixed>|null
     */
    private function resolveConnection(array $tableIdByName, array $tableColorById, array $rowIndex, string $sourceTable, string $sourceCol, string $targetTable, string $targetCol): ?array
    {
        $sourceTableId = $tableIdByName[$sourceTable] ?? null;
        $targetTableId = $tableIdByName[$targetTable] ?? null;
        $sourceRow = $sourceTableId ? ($rowIndex[$sourceTableId][$sourceCol] ?? null) : null;
        $targetRow = $targetTableId ? ($rowIndex[$targetTableId][$targetCol] ?? null) : null;

        if (! $sourceRow || ! $targetRow) {
            return null;
        }

        $color = $tableColorById[$targetRow['parentNode']] ?? '#3d7a5c';

        return [
            'id' => uniqid('e-'),
            'type' => 'chickenFoot',
            'source' => $sourceRow['id'],
            'target' => $targetRow['id'],
            'sourceHandle' => 'source-right',
            'targetHandle' => 'target-left',
            'updatable' => true,
            'style' => ['stroke' => $color],
            'data' => ['relationshipType' => 'one-to-many', 'markerStart' => 'url(#chickenFoot)', 'markerEnd' => 'none', 'color' => $color],
        ];
    }
}
