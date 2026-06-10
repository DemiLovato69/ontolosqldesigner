<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\HierarchicalDiagramLayoutService;
use PHPUnit\Framework\TestCase;

class HierarchicalDiagramLayoutServiceTest extends TestCase
{
    private HierarchicalDiagramLayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HierarchicalDiagramLayoutService;
    }

    public function test_places_dependencies_in_later_hierarchy_ranks(): void
    {
        $tables = $this->tables('users', 'posts', 'comments');
        $rows = [];
        $result = $this->byId($this->service->layout($tables, $rows, [
            ['source' => 'users', 'target' => 'posts'],
            ['source' => 'posts', 'target' => 'comments'],
        ]));

        $this->assertLessThan($result['posts']['position']['x'], $result['users']['position']['x']);
        $this->assertLessThan($result['comments']['position']['x'], $result['posts']['position']['x']);
        $this->assertSame(50, $result['users']['position']['y']);
    }

    public function test_vertically_packs_variable_height_tables_without_overlap(): void
    {
        $tables = $this->tables('short', 'tall');
        $rows = [];
        for ($index = 0; $index < 5; $index++) {
            $rows[] = ['id' => "row-{$index}", 'type' => 'row', 'parentNode' => 'short'];
        }

        $result = $this->byId($this->service->layout($tables, $rows, []));
        $firstBottom = $result['short']['position']['y'] + (6 * 40);

        $this->assertGreaterThanOrEqual($firstBottom + 100, $result['tall']['position']['y']);
    }

    public function test_expands_cycles_across_deterministic_ranks(): void
    {
        $tables = $this->tables('a', 'b', 'c');
        $relationships = [
            ['source' => 'a', 'target' => 'b'],
            ['source' => 'b', 'target' => 'c'],
            ['source' => 'c', 'target' => 'a'],
        ];
        $rows = [];

        $first = $this->byId($this->service->layout($tables, $rows, $relationships));
        $second = $this->byId($this->service->layout($tables, $rows, $relationships));
        $xPositions = array_column(array_map(fn (array $table): array => $table['position'], $first), 'x');

        $this->assertCount(3, array_unique($xPositions));
        $this->assertSame($first, $second);
    }

    public function test_sizes_table_and_rows_for_the_longest_row_name(): void
    {
        $tables = $this->tables('users');
        $rows = [[
            'id' => 'row-1',
            'type' => 'row',
            'label' => str_repeat('long_column_name_', 5),
            'parentNode' => 'users',
            'style' => ['width' => '350px'],
        ]];

        $result = $this->byId($this->service->layout($tables, $rows, []));
        $expectedWidth = 280 + (strlen($rows[0]['label']) * 11);

        $this->assertSame($expectedWidth.'px', $result['users']['style']['width']);
        $this->assertSame($expectedWidth.'px', $rows[0]['style']['width']);
    }

    public function test_uses_wider_spacing_for_large_diagrams(): void
    {
        $tables = [];
        for ($index = 0; $index < 100; $index++) {
            $tables[] = [
                'id' => "table-{$index}",
                'type' => 'table',
                'label' => "table-{$index}",
                'position' => ['x' => 0, 'y' => 0],
            ];
        }
        $rows = [];
        $relationships = [['source' => 'table-0', 'target' => 'table-99']];
        $result = $this->byId($this->service->layout($tables, $rows, $relationships));

        $this->assertSame(810, $result['table-99']['position']['x']);
        $this->assertSame(270, $result['table-1']['position']['y']);
    }

    /** @return list<array<string, mixed>> */
    private function tables(string ...$ids): array
    {
        return array_map(fn (string $id): array => [
            'id' => $id,
            'type' => 'table',
            'label' => $id,
            'position' => ['x' => 0, 'y' => 0],
        ], $ids);
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function byId(array $tables): array
    {
        $result = [];
        foreach ($tables as $table) {
            $result[$table['id']] = $table;
        }

        return $result;
    }
}
