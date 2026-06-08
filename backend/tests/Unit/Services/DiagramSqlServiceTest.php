<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Diagram;
use App\Jobs\ImportDiagramSchemaJob;
use App\Services\DiagramSqlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DiagramSqlServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DiagramSqlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DiagramSqlService::class);
    }

    // --- importSchema / exportScript ---

    public function test_import_schema(): void
    {
        $diagram = Diagram::factory()->create();
        $sql = 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL);';
        $schema = $this->service->importSchema($diagram, json_encode($sql));
        $arr = json_decode($schema, true);
        $this->assertCount(1, array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'table'));
    }

    public function test_export_script(): void
    {
        $diagram = Diagram::factory()->create([
            'schema' => [
                ['id' => 't1', 'type' => 'table', 'label' => 'users'],
                ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false]],
            ],
            'db_type' => 'mysql',
        ]);
        $result = $this->service->exportScript($diagram);
        $this->assertIsString($result);
        $this->assertStringContainsString('users', $result);
    }

    public function test_create_script_emits_table_and_row_notes(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users', 'data' => ['note' => "Application users\nManaged locally"]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'name', 'parentNode' => 't1', 'data' => [
                'sqlType' => 'VARCHAR(255)',
                'nullable' => false,
                'comment' => "User's display name",
            ]],
        ]);

        $script = $this->service->createScript($schema, 'mysql');

        $this->assertStringContainsString("-- Application users\n-- Managed locally", $script);
        $this->assertStringContainsString("COMMENT 'User''s display name'", $script);
    }

    public function test_export_script_for_ontology_diagram_returns_maker_module(): void
    {
        $diagram = Diagram::factory()->create([
            'schema' => [
                ['id' => 't1', 'type' => 'table', 'label' => 'users'],
                ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'STRING', 'nullable' => false, 'unsigned' => false]],
            ],
            'db_type' => 'ontology',
        ]);

        $result = $this->service->exportScript($diagram);

        $this->assertStringContainsString('import { defineObject } from "@osdk/maker";', $result);
        $this->assertStringContainsString('export const users = defineObject({', $result);
    }

    public function test_create_script_my_sql_skips_invalid_connection(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false]],
            ['sourceNode' => ['id' => 'nonexistent'], 'targetNode' => ['id' => 'r1']],
            ['ignored_item' => true],
        ]);
        $script = $this->service->createScript($schema);
        $this->assertStringNotContainsString('FOREIGN KEY', $script);
    }

    public function test_create_script_my_sql_unique_together_invalid_cols_skipped(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'things', 'data' => ['uniqueTogether' => [['nonexistent']]]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false]],
        ]);
        $this->assertStringNotContainsString('UNIQUE KEY', $this->service->createScript($schema));
    }

    public function test_create_script_skips_index_like_rows(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'tbl'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false]],
            ['id' => 'r2', 'type' => 'row', 'label' => 'idx', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => 'INDEX', 'nullable' => false, 'unsigned' => false]],
        ]);
        $script = $this->service->createScript($schema);
        $this->assertStringNotContainsString('`idx`', $script);
    }

    // --- createScript PostgreSQL ---

    public function test_create_script_postgresql_identifiers(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'SERIAL', 'nullable' => false, 'unsigned' => false]],
        ]);
        $script = $this->service->createScript($schema, 'postgresql');
        $this->assertStringContainsString('"users"', $script);
        $this->assertStringNotContainsString('`', $script);
    }

    public function test_create_script_postgresql_strips_unsigned(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'products'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INTEGER', 'nullable' => false, 'unsigned' => true]],
        ]);
        $this->assertStringNotContainsString('UNSIGNED', $this->service->createScript($schema, 'postgresql'));
    }

    // --- createJson ---

    public function test_create_json(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users'],
            ['id' => 't2', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => true, 'defaultValue' => '5', 'comment' => 'pk']],
            ['id' => 'r2', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 't2', 'data' => ['keyMod' => null, 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'defaultValue' => '', 'comment' => '']],
            ['sourceNode' => ['id' => 'r2'], 'targetNode' => ['id' => 'r1']],
        ]);
        $result = $this->service->createJson($schema);
        $this->assertCount(2, $result['tables']);
        $this->assertCount(1, $result['foreignKeys']);
        $col = $result['tables'][0]['columns'][0];
        $this->assertTrue($col['unsigned']);
        $this->assertEquals('PRIMARY KEY', $col['key']);
        $this->assertEquals('5', $col['default_value']);
        $this->assertEquals('pk', $col['comment']);
        $this->assertEquals('posts', $result['foreignKeys'][0]['table']);
    }

    public function test_create_json_skips_invalid_connection(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false]],
            ['sourceNode' => ['id' => 'bad'], 'targetNode' => ['id' => 'r1']],
        ]);
        $result = $this->service->createJson($schema);
        $this->assertEmpty($result['foreignKeys']);
    }

    // --- createSchema MySQL ---

    public function test_create_schema_my_sql_basic(): void
    {
        $sql = 'CREATE TABLE users (id INT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE);
                CREATE TABLE posts (id INT UNSIGNED NOT NULL PRIMARY KEY, user_id INT UNSIGNED NOT NULL);
                ALTER TABLE posts ADD FOREIGN KEY (user_id) REFERENCES users(id);';
        $arr = json_decode($this->service->createSchema($sql), true);
        $this->assertCount(2, array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'table'));
        $this->assertCount(5, array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'));
        $this->assertCount(1, array_filter($arr, fn ($i) => isset($i['source'], $i['target'])));
    }

    public function test_create_schema_my_sql_separate_constraints(): void
    {
        $sql = 'CREATE TABLE products (id INT NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id), UNIQUE (name));';
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('PRIMARY KEY', $rows['id']['data']['keyMod']);
        $this->assertEquals('UNIQUE', $rows['name']['data']['keyMod']);
    }

    public function test_create_schema_my_sql_unique_together(): void
    {
        $sql = 'CREATE TABLE t (id INT NOT NULL, a INT NOT NULL, b INT NOT NULL, PRIMARY KEY (id), UNIQUE (a, b));';
        $arr = json_decode($this->service->createSchema($sql), true);
        $tables = array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'table');
        $table = reset($tables);
        $this->assertCount(2, $table['data']['uniqueTogether'][0]);
    }

    public function test_create_schema_my_sql_nullable(): void
    {
        $sql = 'CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(100) NOT NULL, note TEXT NULL);';
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertFalse($rows['name']['data']['nullable']);
        $this->assertTrue($rows['note']['data']['nullable']);
    }

    public function test_create_schema_my_sql_complex_types(): void
    {
        $sql = "CREATE TABLE t (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, amt DECIMAL(12,4) NOT NULL, status ENUM('a','b') NOT NULL, meta JSON NULL, ts DATETIME(6) NOT NULL);";
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('DECIMAL(12,4)', $rows['amt']['data']['sqlType']);
        $this->assertEquals("ENUM('a','b')", $rows['status']['data']['sqlType']);
        $this->assertEquals('DATETIME(6)', $rows['ts']['data']['sqlType']);
    }

    public function test_create_schema_my_sql_if_not_exists(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, name VARCHAR(100) NOT NULL);';
        $arr = json_decode($this->service->createSchema($sql), true);
        $tables = array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'table');
        $this->assertCount(1, $tables);
        $this->assertEquals('users', reset($tables)['label']);
    }

    public function test_create_schema_my_sql_backticks(): void
    {
        $sql = 'CREATE TABLE `users` (`id` INT UNSIGNED NOT NULL PRIMARY KEY, `full_name` VARCHAR(255) NOT NULL);';
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('INT', $rows['id']['data']['sqlType']);
        $this->assertEquals('VARCHAR(255)', $rows['full_name']['data']['sqlType']);
    }

    public function test_create_schema_my_sql_inline_foreign_key(): void
    {
        $sql = 'CREATE TABLE users (id INT PRIMARY KEY);
                CREATE TABLE posts (id INT PRIMARY KEY, user_id INT, FOREIGN KEY (user_id) REFERENCES users(id));';
        $arr = json_decode($this->service->createSchema($sql), true);
        $this->assertCount(1, array_filter($arr, fn ($i) => isset($i['source'], $i['target'])));
    }

    public function test_create_schema_my_sql_empty(): void
    {
        $this->assertEquals('[]', $this->service->createSchema(''));
    }

    public function test_create_schema_my_sql_invalid(): void
    {
        $this->assertEquals('[]', $this->service->createSchema('INVALID SQL'));
    }

    // --- createSchema PostgreSQL ---

    public function test_create_schema_postgresql_basic(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS "users" ("id" SERIAL NOT NULL PRIMARY KEY, "name" VARCHAR(255) NOT NULL);
                CREATE TABLE IF NOT EXISTS "posts" ("id" SERIAL NOT NULL PRIMARY KEY, "user_id" INTEGER NOT NULL);
                ALTER TABLE "posts" ADD FOREIGN KEY ("user_id") REFERENCES "users"("id");';
        $arr = json_decode($this->service->createSchema($sql), true);
        $this->assertCount(2, array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'table'));
        $this->assertCount(1, array_filter($arr, fn ($i) => isset($i['source'], $i['target'])));
    }

    public function test_create_schema_postgresql_types(): void
    {
        $sql = 'CREATE TABLE "items" ("id" BIGSERIAL NOT NULL PRIMARY KEY, "score" NUMERIC(10,2) NOT NULL, "active" BOOLEAN NOT NULL, "meta" JSONB NULL);';
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('BIGSERIAL', $rows['id']['data']['sqlType']);
        $this->assertEquals('NUMERIC(10,2)', $rows['score']['data']['sqlType']);
        $this->assertTrue($rows['meta']['data']['nullable']);
        $this->assertFalse($rows['active']['data']['nullable']);
    }

    public function test_create_schema_postgresql_table_constraints(): void
    {
        $sql = 'CREATE TABLE "products" ("id" INTEGER NOT NULL, "sku" VARCHAR(64) NOT NULL, PRIMARY KEY ("id"), UNIQUE ("sku"));';
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('PRIMARY KEY', $rows['id']['data']['keyMod']);
        $this->assertEquals('UNIQUE', $rows['sku']['data']['keyMod']);
    }

    // --- createMigration ---

    public function test_create_migration_all_column_types(): void
    {
        $row = fn (string $id, string $label, string $type, bool $nullable = false, bool $unsigned = false, ?string $key = null) => [
            'id' => $id, 'type' => 'row', 'label' => $label, 'parentNode' => 't1',
            'data' => ['keyMod' => $key, 'sqlType' => $type, 'nullable' => $nullable, 'unsigned' => $unsigned, 'defaultValue' => null, 'comment' => null],
        ];

        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'all_types'],
            $row('r1', 'c_bool_tiny', 'TINYINT(1)', false, false, 'PRIMARY KEY'),
            $row('r2', 'c_utinyint', 'TINYINT', false, true),
            $row('r3', 'c_tinyint', 'TINYINT'),
            $row('r4', 'c_usmallint', 'SMALLINT', false, true),
            $row('r5', 'c_smallint', 'SMALLINT'),
            $row('r6', 'c_umediumint', 'MEDIUMINT', false, true),
            $row('r7', 'c_mediumint', 'MEDIUMINT'),
            $row('r8', 'c_ubigint', 'BIGINT', false, true),
            $row('r9', 'c_bigint', 'BIGINT'),
            $row('r10', 'c_uint', 'INT', false, true),
            $row('r11', 'c_int', 'INT'),
            $row('r12', 'c_varchar100', 'VARCHAR(100)'),
            $row('r13', 'c_varchar255', 'VARCHAR(255)'),
            $row('r14', 'c_char', 'CHAR(10)'),
            $row('r15', 'c_char_bare', 'CHAR'),
            $row('r16', 'c_longtext', 'LONGTEXT'),
            $row('r17', 'c_medtext', 'MEDIUMTEXT'),
            $row('r18', 'c_tinytext', 'TINYTEXT'),
            $row('r19', 'c_text', 'TEXT', true),
            $row('r20', 'c_dec_scale', 'DECIMAL(10,2)'),
            $row('r21', 'c_dec_prec', 'DECIMAL(10)'),
            $row('r22', 'c_dec_bare', 'DECIMAL'),
            $row('r23', 'c_double', 'DOUBLE'),
            $row('r24', 'c_float', 'FLOAT'),
            $row('r25', 'c_datetime', 'DATETIME'),
            $row('r26', 'c_timestamp', 'TIMESTAMP'),
            $row('r27', 'c_date', 'DATE'),
            $row('r28', 'c_time', 'TIME'),
            $row('r29', 'c_year', 'YEAR'),
            $row('r30', 'c_bool', 'BOOL'),
            $row('r31', 'c_json', 'JSON'),
            $row('r32', 'c_blob', 'BLOB'),
            $row('r33', 'c_enum', "ENUM('a','b')"),
            $row('r34', 'c_serial', 'SERIAL'),
            $row('r35', 'c_idx', 'INDEX'),
        ]);

        $files = $this->service->createMigration($schema);
        $this->assertCount(1, $files);
        $content = $files[0]['content'];

        $this->assertStringContainsString("boolean('c_bool_tiny')", $content);
        $this->assertStringContainsString("unsignedTinyInteger('c_utinyint')", $content);
        $this->assertStringContainsString("tinyInteger('c_tinyint')", $content);
        $this->assertStringContainsString("unsignedSmallInteger('c_usmallint')", $content);
        $this->assertStringContainsString("smallInteger('c_smallint')", $content);
        $this->assertStringContainsString("unsignedMediumInteger('c_umediumint')", $content);
        $this->assertStringContainsString("mediumInteger('c_mediumint')", $content);
        $this->assertStringContainsString("unsignedBigInteger('c_ubigint')", $content);
        $this->assertStringContainsString("bigInteger('c_bigint')", $content);
        $this->assertStringContainsString("unsignedInteger('c_uint')", $content);
        $this->assertStringContainsString("integer('c_int')", $content);
        $this->assertStringContainsString("string('c_varchar100', 100)", $content);
        $this->assertStringContainsString("string('c_varchar255')", $content);
        $this->assertStringContainsString("char('c_char', 10)", $content);
        $this->assertStringContainsString("char('c_char_bare')", $content);
        $this->assertStringContainsString("longText('c_longtext')", $content);
        $this->assertStringContainsString("mediumText('c_medtext')", $content);
        $this->assertStringContainsString("tinyText('c_tinytext')", $content);
        $this->assertStringContainsString("text('c_text')", $content);
        $this->assertStringContainsString("decimal('c_dec_scale', 10, 2)", $content);
        $this->assertStringContainsString("decimal('c_dec_prec', 10)", $content);
        $this->assertStringContainsString("decimal('c_dec_bare')", $content);
        $this->assertStringContainsString("double('c_double')", $content);
        $this->assertStringContainsString("float('c_float')", $content);
        $this->assertStringContainsString("dateTime('c_datetime')", $content);
        $this->assertStringContainsString("timestamp('c_timestamp')", $content);
        $this->assertStringContainsString("date('c_date')", $content);
        $this->assertStringContainsString("time('c_time')", $content);
        $this->assertStringContainsString("year('c_year')", $content);
        $this->assertStringContainsString("boolean('c_bool')", $content);
        $this->assertStringContainsString("json('c_json')", $content);
        $this->assertStringContainsString("binary('c_blob')", $content);
        $this->assertStringContainsString("enum('c_enum',", $content);
        $this->assertStringContainsString("string('c_serial')", $content);
        $this->assertStringContainsString('->nullable()', $content);
        $this->assertStringContainsString('->primary()', $content);
        $this->assertStringNotContainsString('c_idx', $content);
    }

    public function test_create_migration_column_modifiers(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'orders'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'status', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => 'VARCHAR(50)', 'nullable' => true, 'unsigned' => false, 'defaultValue' => 'pending', 'comment' => 'order status']],
            ['id' => 'r2', 'type' => 'row', 'label' => 'qty', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'defaultValue' => '1', 'comment' => null]],
            ['id' => 'r3', 'type' => 'row', 'label' => 'deleted_at', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => 'TIMESTAMP', 'nullable' => true, 'unsigned' => false, 'defaultValue' => 'NULL', 'comment' => null]],
        ]);
        $content = $this->service->createMigration($schema)[0]['content'];
        $this->assertStringContainsString("->default('pending')", $content);
        $this->assertStringContainsString("->comment('order status')", $content);
        $this->assertStringContainsString('->default(1)', $content);
        $this->assertStringContainsString('->default(null)', $content);
    }

    public function test_create_migration_with_foreign_keys(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'users'],
            ['id' => 't2', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
            ['id' => 'r2', 'type' => 'row', 'label' => 'id', 'parentNode' => 't2', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
            ['id' => 'r3', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 't2', 'data' => ['keyMod' => null, 'sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
            ['sourceNode' => ['id' => 'r1'], 'targetNode' => ['id' => 'r3']],
        ]);
        $files = $this->service->createMigration($schema);
        $this->assertCount(2, $files);
        $postsFile = collect($files)->first(fn ($f) => str_contains($f['filename'], 'posts'));
        $this->assertStringContainsString("foreign('user_id')", $postsFile['content']);
        $this->assertStringContainsString("references('id')->on('users')", $postsFile['content']);
    }

    // --- ENUM export ---

    public function test_create_script_mysql_enum_inline(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'orders', 'data' => ['uniqueTogether' => [], 'fulltextIndexes' => []]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'status', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => "ENUM('pending','active','done')", 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
        ]);
        $script = $this->service->createScript($schema, 'mysql');
        // MySQL keeps enum inline — no CREATE TYPE, values appear verbatim
        $this->assertStringNotContainsString('CREATE TYPE', $script);
        $this->assertStringContainsString("ENUM('pending','active','done')", $script);
    }

    public function test_create_script_postgresql_enum_generates_create_type(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'orders', 'data' => ['uniqueTogether' => [], 'fulltextIndexes' => []]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'status', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => "ENUM('pending','active','done')", 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
        ]);
        $script = $this->service->createScript($schema, 'postgresql');
        // PostgreSQL emits a separate CREATE TYPE ... AS ENUM before the table
        $this->assertStringContainsString("CREATE TYPE \"orders_status\" AS ENUM ('pending','active','done')", $script);
        $this->assertStringContainsString('"status" "orders_status"', $script);
        $this->assertStringNotContainsString('VARCHAR(255)', $script);
    }

    // --- ENUM import ---

    public function test_create_schema_mysql_enum_import(): void
    {
        $sql = "CREATE TABLE orders (`id` INT NOT NULL PRIMARY KEY, `status` ENUM('pending','active','done') NOT NULL);";
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals("ENUM('pending','active','done')", $rows['status']['data']['sqlType']);
    }

    public function test_create_schema_postgresql_enum_import_via_create_type(): void
    {
        $sql = "CREATE TYPE \"orders_status\" AS ENUM ('pending','active','done');
                CREATE TABLE IF NOT EXISTS \"orders\" (\"id\" SERIAL NOT NULL PRIMARY KEY, \"status\" \"orders_status\" NOT NULL);";
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals("ENUM('pending','active','done')", $rows['status']['data']['sqlType']);
    }

    public function test_create_schema_postgresql_enum_roundtrip(): void
    {
        // Build schema with an ENUM column
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'orders', 'data' => ['uniqueTogether' => [], 'fulltextIndexes' => []]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'SERIAL', 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
            ['id' => 'r2', 'type' => 'row', 'label' => 'status', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => "ENUM('pending','active')", 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
        ]);
        // Export to PostgreSQL SQL — generates CREATE TYPE ... AS ENUM
        $sql = $this->service->createScript($schema, 'postgresql');
        // Re-import: status is resolved back to ENUM via the CREATE TYPE statement
        $arr = json_decode($this->service->createSchema($sql), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals("ENUM('pending','active')", $rows['status']['data']['sqlType']);
    }

    public function test_create_schema_for_ontology_normalizes_imported_sql_types(): void
    {
        $sql = "CREATE TABLE assets (
            id BIGSERIAL PRIMARY KEY,
            active TINYINT(1) NOT NULL,
            score NUMERIC(12,4),
            payload JSONB,
            photo BYTEA,
            located_at TIMESTAMP,
            status ENUM('draft','published'),
            tags SET('internal','external')
        );";

        $arr = json_decode($this->service->createSchema($sql, 'ontology'), true);
        $rows = array_column(array_filter($arr, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');

        $this->assertEquals('LONG', $rows['id']['data']['sqlType']);
        $this->assertEquals('BOOLEAN', $rows['active']['data']['sqlType']);
        $this->assertEquals('DECIMAL(12,4)', $rows['score']['data']['sqlType']);
        $this->assertEquals('STRING', $rows['payload']['data']['sqlType']);
        $this->assertEquals('ATTACHMENT', $rows['photo']['data']['sqlType']);
        $this->assertEquals('TIMESTAMP', $rows['located_at']['data']['sqlType']);
        $this->assertEquals("ENUM('draft','published')", $rows['status']['data']['sqlType']);
        $this->assertEquals("ENUM('internal','external')", $rows['tags']['data']['sqlType']);
    }

    public function test_import_schema_for_ontology_normalizes_imported_sql_types(): void
    {
        $diagram = Diagram::factory()->create(['db_type' => 'ontology']);

        $this->service->importSchema($diagram, 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), created_at DATETIME);');

        $rows = array_column(array_filter($diagram->refresh()->schema, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('INTEGER', $rows['id']['data']['sqlType']);
        $this->assertEquals('STRING', $rows['name']['data']['sqlType']);
        $this->assertEquals('TIMESTAMP', $rows['created_at']['data']['sqlType']);
    }

    public function test_import_job_for_ontology_normalizes_imported_sql_types(): void
    {
        $diagram = Diagram::factory()->create([
            'db_type' => 'ontology',
            'script' => 'CREATE TABLE users (id BIGINT PRIMARY KEY, birth_date DATE);',
        ]);

        (new ImportDiagramSchemaJob($diagram))->handle($this->service);

        $rows = array_column(array_filter($diagram->refresh()->schema, fn ($i) => ($i['type'] ?? null) === 'row'), null, 'label');
        $this->assertEquals('LONG', $rows['id']['data']['sqlType']);
        $this->assertEquals('DATE', $rows['birth_date']['data']['sqlType']);
    }

    public function test_create_migration_enum_values(): void
    {
        $schema = json_encode([
            ['id' => 't1', 'type' => 'table', 'label' => 'orders'],
            ['id' => 'r1', 'type' => 'row', 'label' => 'status', 'parentNode' => 't1', 'data' => ['keyMod' => null, 'sqlType' => "ENUM('pending','active','done')", 'nullable' => false, 'unsigned' => false, 'defaultValue' => null, 'comment' => null]],
        ]);
        $content = $this->service->createMigration($schema)[0]['content'];
        $this->assertStringContainsString("enum('status', ['pending','active','done'])", $content);
    }
}
