<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidSchemaException;
use App\Services\MakerDefinitionImportService;
use Tests\TestCase;

class MakerDefinitionImportServiceTest extends TestCase
{
    public function test_rejects_duplicate_property_identifiers_from_runtime(): void
    {
        $script = $this->fakeRuntime([
            'objectTypes' => [
                $this->objectType('users', [['id' => 'id', 'rid' => 'id', 'apiName' => 'id']]),
                $this->objectType('posts', [['id' => 'id', 'rid' => 'id', 'apiName' => 'id']]),
            ],
            'relations' => [],
            'valueTypes' => [],
        ]);

        config()->set('services.maker_import.script', $script);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('duplicate property identifier: id');

        app(MakerDefinitionImportService::class)->convert('ignored');
    }

    public function test_rejects_relations_with_unresolved_properties(): void
    {
        $script = $this->fakeRuntime([
            'objectTypes' => [
                $this->objectType('users', [['id' => 'id', 'rid' => 'users::id', 'apiName' => 'id']]),
                $this->objectType('posts', [
                    ['id' => 'id', 'rid' => 'posts::id', 'apiName' => 'id'],
                    ['id' => 'userId', 'rid' => 'posts::userId', 'apiName' => 'userId'],
                ]),
            ],
            'relations' => [[
                'id' => 'user-posts',
                'rid' => 'user-posts',
                'definition' => [
                    'type' => 'oneToMany',
                    'oneToMany' => [
                        'objectTypeRidOneSide' => 'users',
                        'objectTypeRidManySide' => 'posts',
                        'manySideForeignKeyPropertyId' => 'userId',
                        'oneSidePrimaryKeyToManySidePropertyMapping' => [
                            'missing' => 'posts::userId',
                        ],
                    ],
                ],
            ]],
            'valueTypes' => [],
        ]);

        config()->set('services.maker_import.script', $script);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('references an unknown property');

        app(MakerDefinitionImportService::class)->convert('ignored');
    }

    /** @param list<array<string, string>> $properties */
    private function objectType(string $rid, array $properties): array
    {
        return [
            'id' => $rid,
            'rid' => $rid,
            'apiName' => $rid,
            'titlePropertyId' => 'id',
            'primaryKeys' => ['id'],
            'properties' => $properties,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function fakeRuntime(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'maker-import-');
        file_put_contents(
            $path,
            'process.stdout.write('.json_encode(json_encode($payload, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR).');'
        );

        return $path;
    }
}
