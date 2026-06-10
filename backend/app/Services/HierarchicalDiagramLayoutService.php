<?php

declare(strict_types=1);

namespace App\Services;

final class HierarchicalDiagramLayoutService
{
    private const START_X = 50;

    private const START_Y = 50;

    private const MIN_TABLE_WIDTH = 400;

    private const LABEL_CHARACTER_WIDTH = 11;

    private const ROW_CHROME_WIDTH = 280;

    private const DEFAULT_RANK_GAP = 160;

    private const LARGE_RANK_GAP = 360;

    private const DEFAULT_NODE_GAP = 100;

    private const LARGE_NODE_GAP = 180;

    private const LARGE_DIAGRAM_TABLE_COUNT = 100;

    private const ROW_HEIGHT = 40;

    /**
     * Place tables in left-to-right hierarchy ranks and vertically order each
     * rank with barycentric sweeps to reduce relationship crossings.
     *
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $rows  Updated with table-specific widths.
     * @param  list<array{source: string, target: string}>  $relationships
     * @return list<array<string, mixed>>
     */
    public function layout(array $tables, array &$rows, array $relationships): array
    {
        if ($tables === []) {
            return [];
        }

        $tableIds = array_column($tables, 'id');
        $tableSet = array_fill_keys($tableIds, true);
        $adjacency = array_fill_keys($tableIds, []);
        $reverse = array_fill_keys($tableIds, []);

        foreach ($relationships as $relationship) {
            $source = $relationship['source'];
            $target = $relationship['target'];
            if ($source === $target || ! isset($tableSet[$source], $tableSet[$target])) {
                continue;
            }
            $adjacency[$source][$target] = true;
            $reverse[$target][$source] = true;
        }

        $components = $this->stronglyConnectedComponents($tableIds, $adjacency);
        $componentByTable = [];
        foreach ($components as $componentId => &$component) {
            sort($component, SORT_STRING);
            foreach ($component as $tableId) {
                $componentByTable[$tableId] = $componentId;
            }
        }
        unset($component);

        $componentAdjacency = array_fill(0, count($components), []);
        $indegree = array_fill(0, count($components), 0);
        foreach ($adjacency as $source => $targets) {
            foreach ($targets as $target => $_) {
                $from = $componentByTable[$source];
                $to = $componentByTable[$target];
                if ($from === $to || isset($componentAdjacency[$from][$to])) {
                    continue;
                }
                $componentAdjacency[$from][$to] = true;
                $indegree[$to]++;
            }
        }

        $componentRanks = array_fill(0, count($components), 0);
        $queue = [];
        foreach ($indegree as $componentId => $degree) {
            if ($degree === 0) {
                $queue[] = $componentId;
            }
        }
        sort($queue, SORT_NUMERIC);

        while ($queue !== []) {
            $componentId = array_shift($queue);
            $nextRank = $componentRanks[$componentId] + count($components[$componentId]);
            foreach (array_keys($componentAdjacency[$componentId]) as $targetId) {
                $componentRanks[$targetId] = max($componentRanks[$targetId], $nextRank);
                $indegree[$targetId]--;
                if ($indegree[$targetId] === 0) {
                    $queue[] = $targetId;
                    sort($queue, SORT_NUMERIC);
                }
            }
        }

        $rankByTable = [];
        foreach ($components as $componentId => $component) {
            foreach ($component as $offset => $tableId) {
                $rankByTable[$tableId] = $componentRanks[$componentId] + $offset;
            }
        }

        $ranks = [];
        foreach ($tables as $table) {
            $ranks[$rankByTable[$table['id']] ?? 0][] = $table['id'];
        }
        ksort($ranks, SORT_NUMERIC);

        $labels = [];
        foreach ($tables as $table) {
            $labels[$table['id']] = strtolower((string) ($table['label'] ?? $table['id']));
        }
        foreach ($ranks as &$rank) {
            usort($rank, fn (string $a, string $b): int => [$labels[$a], $a] <=> [$labels[$b], $b]);
        }
        unset($rank);

        for ($iteration = 0; $iteration < 4; $iteration++) {
            $this->orderRanks($ranks, $reverse, true);
            $this->orderRanks($ranks, $adjacency, false);
        }

        $rowCountByTable = array_fill_keys($tableIds, 0);
        $longestLabelByTable = [];
        foreach ($tables as $table) {
            $longestLabelByTable[$table['id']] = strlen((string) ($table['label'] ?? ''));
        }
        foreach ($rows as $row) {
            $parentId = $row['parentNode'] ?? null;
            if (is_string($parentId) && isset($rowCountByTable[$parentId])) {
                $rowCountByTable[$parentId]++;
                $longestLabelByTable[$parentId] = max(
                    $longestLabelByTable[$parentId],
                    strlen((string) ($row['label'] ?? ''))
                );
            }
        }

        $widthByTable = [];
        foreach ($tableIds as $tableId) {
            $widthByTable[$tableId] = max(
                self::MIN_TABLE_WIDTH,
                self::ROW_CHROME_WIDTH + ($longestLabelByTable[$tableId] * self::LABEL_CHARACTER_WIDTH)
            );
        }

        $isLargeDiagram = count($tables) >= self::LARGE_DIAGRAM_TABLE_COUNT;
        $rankGap = $isLargeDiagram ? self::LARGE_RANK_GAP : self::DEFAULT_RANK_GAP;
        $nodeGap = $isLargeDiagram ? self::LARGE_NODE_GAP : self::DEFAULT_NODE_GAP;
        $rankX = [];
        $nextX = self::START_X;
        foreach ($ranks as $rankNumber => $rank) {
            $rankX[$rankNumber] = $nextX;
            $rankWidth = max(array_map(fn (string $tableId): int => $widthByTable[$tableId], $rank));
            $nextX += $rankWidth + $rankGap;
        }

        $positions = [];
        foreach ($ranks as $rankNumber => $rank) {
            $y = self::START_Y;
            foreach ($rank as $tableId) {
                $positions[$tableId] = [
                    'x' => $rankX[$rankNumber],
                    'y' => $y,
                ];
                $height = self::ROW_HEIGHT * (1 + $rowCountByTable[$tableId]);
                $y += $height + $nodeGap;
            }
        }

        foreach ($tables as &$table) {
            $table['position'] = $positions[$table['id']] ?? ['x' => self::START_X, 'y' => self::START_Y];
            $table['style']['width'] = $widthByTable[$table['id']].'px';
        }
        unset($table);

        foreach ($rows as &$row) {
            $parentId = $row['parentNode'] ?? null;
            if (is_string($parentId) && isset($widthByTable[$parentId])) {
                $row['style']['width'] = $widthByTable[$parentId].'px';
            }
        }
        unset($row);

        return $tables;
    }

    /**
     * @param  array<int, list<string>>  $ranks
     * @param  array<string, array<string, true>>  $neighbors
     */
    private function orderRanks(array &$ranks, array $neighbors, bool $forward): void
    {
        $rankNumbers = array_keys($ranks);
        if (! $forward) {
            $rankNumbers = array_reverse($rankNumbers);
        }

        $positions = $this->rankPositions($ranks);
        foreach ($rankNumbers as $rankNumber) {
            $decorated = [];
            foreach ($ranks[$rankNumber] as $stableIndex => $tableId) {
                $neighborPositions = [];
                foreach (array_keys($neighbors[$tableId] ?? []) as $neighborId) {
                    if (isset($positions[$neighborId])) {
                        $neighborPositions[] = $positions[$neighborId];
                    }
                }
                $decorated[] = [
                    'id' => $tableId,
                    'barycenter' => $neighborPositions === []
                        ? $positions[$tableId]
                        : array_sum($neighborPositions) / count($neighborPositions),
                    'stable' => $stableIndex,
                ];
            }
            usort($decorated, fn (array $a, array $b): int => [$a['barycenter'], $a['stable']] <=> [$b['barycenter'], $b['stable']]);
            $ranks[$rankNumber] = array_column($decorated, 'id');
            $positions = $this->rankPositions($ranks);
        }
    }

    /**
     * @param  array<int, list<string>>  $ranks
     * @return array<string, int>
     */
    private function rankPositions(array $ranks): array
    {
        $positions = [];
        foreach ($ranks as $rank) {
            foreach ($rank as $index => $tableId) {
                $positions[$tableId] = $index;
            }
        }

        return $positions;
    }

    /**
     * Iterative Kosaraju decomposition avoids recursion limits on large imports.
     *
     * @param  list<string>  $tableIds
     * @param  array<string, array<string, true>>  $adjacency
     * @return list<list<string>>
     */
    private function stronglyConnectedComponents(array $tableIds, array $adjacency): array
    {
        $visited = [];
        $finishOrder = [];

        foreach ($tableIds as $start) {
            if (isset($visited[$start])) {
                continue;
            }
            $stack = [[$start, false]];
            while ($stack !== []) {
                [$tableId, $expanded] = array_pop($stack);
                if ($expanded) {
                    $finishOrder[] = $tableId;

                    continue;
                }
                if (isset($visited[$tableId])) {
                    continue;
                }
                $visited[$tableId] = true;
                $stack[] = [$tableId, true];
                foreach (array_reverse(array_keys($adjacency[$tableId] ?? [])) as $targetId) {
                    if (! isset($visited[$targetId])) {
                        $stack[] = [$targetId, false];
                    }
                }
            }
        }

        $reverse = array_fill_keys($tableIds, []);
        foreach ($adjacency as $source => $targets) {
            foreach ($targets as $target => $_) {
                $reverse[$target][$source] = true;
            }
        }

        $assigned = [];
        $components = [];
        foreach (array_reverse($finishOrder) as $start) {
            if (isset($assigned[$start])) {
                continue;
            }
            $component = [];
            $stack = [$start];
            $assigned[$start] = true;
            while ($stack !== []) {
                $tableId = array_pop($stack);
                $component[] = $tableId;
                foreach (array_keys($reverse[$tableId] ?? []) as $sourceId) {
                    if (! isset($assigned[$sourceId])) {
                        $assigned[$sourceId] = true;
                        $stack[] = $sourceId;
                    }
                }
            }
            $components[] = $component;
        }

        return $components;
    }
}
