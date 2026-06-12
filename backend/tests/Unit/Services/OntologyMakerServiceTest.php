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
        $this->assertStringContainsString('cardinality: "OneToMany"', $module);
    }

    public function test_exports_custom_value_type_constraints_and_property_reference(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'STRING',
            ]],
            ['id' => 'email', 'type' => 'row', 'label' => 'email', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'valueTypeId' => 'email-type',
            ]],
        ]);
        $valueTypes = [[
            'id' => 'email-type',
            'apiName' => 'emailAddress',
            'displayName' => 'Email Address',
            'description' => 'Validated email address',
            'version' => '1.2.0',
            'baseType' => ['type' => 'string'],
            'constraints' => [
                [
                    'id' => 'regex',
                    'type' => 'regex',
                    'regexPattern' => '^[^@]+@[^@]+\\.[^@]+$',
                    'usePartialMatch' => false,
                    'failureMessage' => 'Must be a valid email',
                ],
                [
                    'id' => 'length',
                    'type' => 'length',
                    'minSize' => 5,
                    'maxSize' => 255,
                    'failureMessage' => 'Email length is invalid',
                ],
            ],
        ]];

        $module = $this->service->createModule($schema, $valueTypes);

        $this->assertStringContainsString('export const emailAddress = defineValueType({', $module);
        $this->assertStringContainsString('description: "Validated email address"', $module);
        $this->assertStringContainsString('regexPattern: "^[^@]+@[^@]+\\\\.[^@]+$"', $module);
        $this->assertStringContainsString('usePartialMatch: false', $module);
        $this->assertStringContainsString('failureMessage: { message: "Must be a valid email" }', $module);
        $this->assertStringContainsString('length: { minSize: 5, maxSize: 255 }', $module);
        $this->assertStringContainsString('version: "1.2.0"', $module);
        $this->assertStringContainsString('"email": { type: "string"', $module);
        $this->assertStringContainsString('valueType: emailAddress', $module);
    }

    public function test_exports_one_level_array_and_struct_value_types(): void
    {
        $schema = json_encode([
            ['id' => 'items', 'type' => 'table', 'label' => 'items'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'items', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'STRING',
            ]],
        ]);
        $valueTypes = [
            [
                'id' => 'tags',
                'apiName' => 'tags',
                'displayName' => 'Tags',
                'version' => '1.0.0',
                'baseType' => ['type' => 'array', 'elementType' => 'string'],
                'constraints' => [],
            ],
            [
                'id' => 'address',
                'apiName' => 'address',
                'displayName' => 'Address',
                'version' => '1.0.0',
                'baseType' => ['type' => 'struct', 'fields' => [
                    ['id' => 'city', 'apiName' => 'city', 'type' => 'string'],
                    ['id' => 'zip', 'apiName' => 'zipCode', 'type' => 'integer'],
                ]],
                'constraints' => [],
            ],
        ];

        $module = $this->service->createModule($schema, $valueTypes);

        $this->assertStringContainsString('type: { type: "array", elementType: "string" }', $module);
        $this->assertStringContainsString('{ identifier: "city", baseType: "string" }', $module);
        $this->assertStringContainsString('{ identifier: "zipCode", baseType: "integer" }', $module);
    }

    public function test_respects_one_to_one_relationship_type(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'profiles', 'type' => 'table', 'label' => 'profiles'],
            ['id' => 'profile_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'profiles', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'profile_user_id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'profiles', 'data' => ['keyMod' => 'FOREIGN KEY', 'sqlType' => 'LONG']],
            ['source' => 'user_id', 'target' => 'profile_user_id', 'data' => ['relationshipType' => 'one-to-one']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('export const userToProfiles = defineLink({', $module);
        $this->assertStringContainsString('manyForeignKeyProperty: "userId"', $module);
        $this->assertStringContainsString('cardinality: "OneToOne"', $module);
    }

    public function test_respects_many_to_one_relationship_type(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'posts', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'post_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'post_user_id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'FOREIGN KEY', 'sqlType' => 'LONG']],
            ['source' => 'post_user_id', 'target' => 'user_id', 'data' => ['relationshipType' => 'many-to-one']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('export const userToPosts = defineLink({', $module);
        $this->assertStringContainsString('object: users', $module);
        $this->assertStringContainsString('object: posts', $module);
        $this->assertStringContainsString('manyForeignKeyProperty: "userId"', $module);
        $this->assertStringContainsString('cardinality: "OneToMany"', $module);
    }

    public function test_respects_many_to_many_relationship_type(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'groups', 'type' => 'table', 'label' => 'groups'],
            ['id' => 'group_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'groups', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['source' => 'user_id', 'target' => 'group_id', 'data' => ['relationshipType' => 'many-to-many']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('export const userToGroups = defineLink({', $module);
        $this->assertStringContainsString('  many: {', $module);
        $this->assertStringContainsString('  toMany: {', $module);
        $this->assertStringNotContainsString('manyForeignKeyProperty', $module);
        $this->assertStringNotContainsString('cardinality:', $module);
    }

    public function test_prefers_current_edge_endpoints_over_stale_cached_nodes(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'posts', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'post_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'post_user_id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'FOREIGN KEY', 'sqlType' => 'LONG']],
            [
                'source' => 'user_id',
                'target' => 'post_user_id',
                'sourceNode' => ['id' => 'post_id'],
                'targetNode' => ['id' => 'user_id'],
                'data' => ['relationshipType' => 'one-to-many'],
            ],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('export const userToPosts = defineLink({', $module);
        $this->assertStringContainsString('manyForeignKeyProperty: "userId"', $module);
        $this->assertSame(1, substr_count($module, 'defineLink({'));
    }

    public function test_collapses_duplicate_relationship_records_after_an_edit(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'posts', 'type' => 'table', 'label' => 'posts'],
            ['id' => 'post_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
            ['id' => 'post_user_id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'posts', 'data' => ['keyMod' => 'FOREIGN KEY', 'sqlType' => 'LONG']],
            ['id' => 'old-edge', 'source' => 'user_id', 'target' => 'post_user_id', 'data' => ['relationshipType' => 'one-to-many']],
            ['id' => 'new-edge', 'source' => 'user_id', 'target' => 'post_user_id', 'data' => ['relationshipType' => 'one-to-one']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertSame(1, substr_count($module, 'defineLink({'));
        $this->assertStringContainsString('export const userToPosts = defineLink({', $module);
        $this->assertStringContainsString('cardinality: "OneToOne"', $module);
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

    public function test_does_not_emit_actions_by_default(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringNotContainsString('defineCreateObjectAction', $module);
        $this->assertStringNotContainsString('defineModifyObjectAction', $module);
        $this->assertStringNotContainsString('defineDeleteObjectAction', $module);
    }

    public function test_emits_only_enabled_crud_actions(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users', 'data' => [
                'ontologyActions' => ['create' => true, 'modify' => false, 'delete' => true],
            ]],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG']],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('import { defineCreateObjectAction, defineDeleteObjectAction, defineObject } from "@osdk/maker";', $module);
        $this->assertStringContainsString('export const createUsersAction = defineCreateObjectAction({', $module);
        $this->assertStringContainsString('export const deleteUsersAction = defineDeleteObjectAction({', $module);
        $this->assertStringContainsString('objectType: users', $module);
        $this->assertStringNotContainsString('defineModifyObjectAction', $module);
    }

    public function test_emits_table_and_row_notes_as_descriptions(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users', 'data' => ['description' => 'Application users']],
            ['id' => 'user_id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'LONG',
                'description' => 'Stable user identifier',
            ]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('description: "Application users"', $module);
        $this->assertStringContainsString('description: "Stable user identifier"', $module);
    }

    public function test_emits_explicit_indexed_for_search_setting(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'LONG',
                'nullable' => false,
            ]],
            ['id' => 'email', 'type' => 'row', 'label' => 'email', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'nullable' => true,
                'indexed' => true,
            ]],
            ['id' => 'nickname', 'type' => 'row', 'label' => 'nickname', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'nullable' => true,
                'indexed' => false,
            ]],
            ['id' => 'city', 'type' => 'row', 'label' => 'city', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'nullable' => true,
            ]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('"email": { type: "string", displayName: "Email", indexedForSearch: true }', $module);
        $this->assertStringContainsString('"city": { type: "string", displayName: "City", indexedForSearch: true }', $module);
        $this->assertStringContainsString('"nickname": { type: "string", displayName: "Nickname" }', $module);
    }

    public function test_emits_supported_primary_key_and_nullability_constraints(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'LONG',
                'nullable' => false,
            ]],
            ['id' => 'name', 'type' => 'row', 'label' => 'full_name', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'nullable' => false,
            ]],
            ['id' => 'nickname', 'type' => 'row', 'label' => 'nickname', 'parentNode' => 'users', 'data' => [
                'sqlType' => 'STRING',
                'nullable' => true,
            ]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('primaryKeyPropertyApiName: "id"', $module);
        $this->assertStringContainsString('"id": { type: "long", displayName: "Id", nullability: { noEmptyCollections: false, noNulls: true }, indexedForSearch: true }', $module);
        $this->assertStringContainsString('"fullName": { type: "string", displayName: "Full Name", nullability: { noEmptyCollections: false, noNulls: true }, indexedForSearch: true }', $module);
        $this->assertStringContainsString('"nickname": { type: "string", displayName: "Nickname", indexedForSearch: true }', $module);
    }

    public function test_emits_constraint_metadata_for_uniques_and_indexes(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users', 'data' => [
                'uniqueTogether' => [['tenant_id', 'external_id']],
                'fulltextIndexes' => [['full_name']],
            ]],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG', 'nullable' => false]],
            ['id' => 'tenant', 'type' => 'row', 'label' => 'tenant_id', 'parentNode' => 'users', 'data' => ['sqlType' => 'LONG', 'nullable' => false]],
            ['id' => 'external', 'type' => 'row', 'label' => 'external_id', 'parentNode' => 'users', 'data' => ['keyMod' => 'UNIQUE', 'sqlType' => 'STRING', 'nullable' => false]],
            ['id' => 'name', 'type' => 'row', 'label' => 'full_name', 'parentNode' => 'users', 'data' => ['keyMod' => 'INDEX', 'sqlType' => 'STRING', 'nullable' => true]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('// - unique: external_id', $module);
        $this->assertStringContainsString('// - index: full_name', $module);
        $this->assertStringContainsString('// - unique together: tenant_id, external_id', $module);
        $this->assertStringContainsString('// - fulltext index: full_name', $module);
        $this->assertStringContainsString('"externalId": { type: "string", displayName: "External Id", nullability: { noEmptyCollections: false, noNulls: true }, indexedForSearch: true }', $module);
        $this->assertStringContainsString('"fullName": { type: "string", displayName: "Full Name", indexedForSearch: true }', $module);
    }

    public function test_skips_invalid_columns_in_table_level_constraints(): void
    {
        $schema = json_encode([
            ['id' => 'users', 'type' => 'table', 'label' => 'users', 'data' => [
                'uniqueTogether' => [['tenant_id', 'missing_column']],
                'fulltextIndexes' => [['missing_column']],
            ]],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'LONG', 'nullable' => false]],
            ['id' => 'tenant', 'type' => 'row', 'label' => 'tenant_id', 'parentNode' => 'users', 'data' => ['sqlType' => 'LONG', 'nullable' => false]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringNotContainsString('missing_column', $module);
        $this->assertStringNotContainsString('unique together: tenant_id', $module);
        $this->assertStringContainsString('"tenantId": { type: "long", displayName: "Tenant Id", nullability: { noEmptyCollections: false, noNulls: true }, indexedForSearch: true }', $module);
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
            'long' => ['LONG', '"long"'],
            'bigint' => ['BIGINT', '"long"'],
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

    public function test_emits_array_vector_struct_and_geohash_properties(): void
    {
        $schema = json_encode([
            ['id' => 'types', 'type' => 'table', 'label' => 'types'],
            ['id' => 'id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'types', 'data' => [
                'keyMod' => 'PRIMARY KEY',
                'sqlType' => 'STRING',
            ]],
            ['id' => 'tags', 'type' => 'row', 'label' => 'tags', 'parentNode' => 'types', 'data' => [
                'sqlType' => 'ARRAY<STRING>',
                'ontologyBaseType' => ['type' => 'ARRAY', 'subType' => ['type' => 'STRING']],
            ]],
            ['id' => 'embedding', 'type' => 'row', 'label' => 'embedding', 'parentNode' => 'types', 'data' => [
                'sqlType' => 'VECTOR(1536)',
                'ontologyBaseType' => ['type' => 'VECTOR', 'dimension' => 1536],
            ]],
            ['id' => 'address', 'type' => 'row', 'label' => 'address', 'parentNode' => 'types', 'data' => [
                'sqlType' => 'STRUCT',
                'ontologyBaseType' => [
                    'type' => 'STRUCT',
                    'structFields' => [
                        ['apiName' => 'street', 'fieldType' => ['type' => 'STRING']],
                        ['apiName' => 'number', 'fieldType' => ['type' => 'INTEGER']],
                    ],
                ],
            ]],
            ['id' => 'location', 'type' => 'row', 'label' => 'location', 'parentNode' => 'types', 'data' => [
                'sqlType' => 'GEOHASH',
                'ontologyBaseType' => ['type' => 'GEOHASH'],
            ]],
        ]);

        $module = $this->service->createModule($schema);

        $this->assertStringContainsString('"tags": { type: "string", array: true', $module);
        $this->assertStringContainsString('"embedding": { type: "vector"', $module);
        $this->assertStringContainsString('"address": { type: { type: "struct", structDefinition: { "street": "string", "number": "integer" } }', $module);
        $this->assertStringContainsString('"location": { type: "geohash"', $module);
    }
}
