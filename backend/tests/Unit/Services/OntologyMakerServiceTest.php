<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OntologyMakerService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OntologyMakerServiceTest extends TestCase
{
    private OntologyMakerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OntologyMakerService::class);
    }

    public function test_creates_objects_value_types_and_links(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'BIGINT']],
            ['id' => 'user_name', 'type' => 'row', 'label' => 'full_name', 'parentNode' => 'users', 'data' => ['sqlType' => 'VARCHAR(255)']],
            ['id' => 'posts', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'post_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'SERIAL']],
            ['id' => 'post_user_id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'posts', 'data' => ['sqlType' => 'BIGINT']],
            ['id' => 'post_status', 'type' => 'row', 'label' => 'status', 'parentNode' => 'posts', 'data' => ['sqlType' => "ENUM('draft','published')"]],
            ['source' => 'user_id', 'target' => 'post_user_id'],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('import { defineLink, defineObject, defineValueType } from "@osdk/maker";', $module);
        $this->assertStringContainsString('export const users = defineObject({', $module);
        $this->assertStringContainsString('titlePropertyApiName: "fullName"', $module);
        $this->assertStringContainsString('"id": { type: "long"', $module);
        $this->assertStringContainsString('oneOf: { values: ["draft", "published"], useIgnoreCase: false }', $module);
        $this->assertStringContainsString('export const userToPosts = defineLink({', $module);
        $this->assertStringContainsString('manyForeignKeyProperty: "userId"', $module);
    }

    public function test_generates_synthetic_composite_primary_key(): void
    {
        $schema = json_encode([
            ['id' => 'table', 'type' => 'table', 'label' => 'playlist_tracks'],
            ['id' => 'playlist', 'type' => 'row', 'label' => 'playlist_id', 'parentNode' => 'table', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INTEGER']],
            ['id' => 'track', 'type' => 'row', 'label' => 'track_id', 'parentNode' => 'table', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'INTEGER']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('primaryKeyPropertyApiName: "playlistIdTrackIdKey"', $module);
        $this->assertStringContainsString('"playlistIdTrackIdKey": { type: "string"', $module);
    }

    public function test_emits_table_and_row_notes_as_descriptions(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users', 'data' => ['note' => 'Application users']],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'LONG',
                'comment' => 'Stable user identifier',
            ]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('description: "Application users"', $module);
        $this->assertStringContainsString('description: "Stable user identifier"', $module);
    }

    #[DataProvider('sqlTypeProvider')]
    public function test_maps_all_canvas_type_families(string $sqlType, string $makerType): void
    {
        $schema = json_encode([
            ['id' => 'table', 'type' => 'table', 'label' => 'types'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'table', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => $sqlType]],
        ]);

        $this->assertStringContainsString('"id": { type: '.$makerType, $this->service->createModule($schema));
    }

    /** @return array<string, array{string, string}> */
    public static function sqlTypeProvider(): array
    {
        return [
            'boolean' => ['TINYINT(1)', '"boolean"'],
            'byte' => ['TINYINT', '"byte"'],
            'short' => ['SMALLINT', '"short"'],
            'integer' => ['INTEGER', '"integer"'],
            'long' => ['BIGINT', '"long"'],
            'decimal' => ['DECIMAL(10,2)', '{ type: "decimal", precision: 10, scale: 2 }'],
            'float' => ['REAL', '"float"'],
            'double' => ['DOUBLE PRECISION', '"double"'],
            'date' => ['DATE', '"date"'],
            'timestamp' => ['TIMESTAMP WITH TIME ZONE', '"timestamp"'],
            'attachment' => ['BYTEA', '"attachment"'],
            'string' => ['JSONB', '"string"'],
            'geopoint' => ['GEOPOINT', '"geopoint"'],
            'geoshape' => ['GEOSHAPE', '"geoshape"'],
            'media reference' => ['MEDIAREFERENCE', '"mediaReference"'],
            'geotime series' => ['GEOTIMESERIES', '"geotimeSeries"'],
        ];
    }
}
