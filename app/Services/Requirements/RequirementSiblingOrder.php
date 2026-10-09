<?php

namespace App\Services\Requirements;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Illuminate\Support\Collection;

/**
 * Orders every level of the requirement tree so a node comes after everything it depends on: a stable topological
 * sort, ties broken by position, then number. A dependency between nodes that are not siblings is lifted to the pair
 * of their ancestors that are (a rule in group X depending on a rule in group Y puts X after Y). requirements:save only
 * guards direct dependencies against loops, so a lifted edge that would close a loop among siblings is skipped (the
 * caller passes dependencies in id order, so the older node's edge wins).
 * 作废 nodes sit after the live ones of their level and take no part in the dependency order.
 */
final class RequirementSiblingOrder
{
    /**
     * @param  Collection<int, Requirement>  $requirements  one project's nodes
     * @param  iterable<array{int, int}>  $dependencies  [dependent id, prerequisite id]
     * @return array<int, list<Requirement>> parent id (0 for roots) => children in order
     */
    public static function childrenByParent(Collection $requirements, iterable $dependencies): array
    {
        $parentById = $requirements->mapWithKeys(fn (Requirement $requirement): array => [$requirement->id => $requirement->parent_id ?? 0])->all();
        $edgesByParent = [];

        foreach ($dependencies as [$dependentId, $prerequisiteId]) {
            $edge = self::liftedEdge(self::path($parentById, $dependentId), self::path($parentById, $prerequisiteId));

            if ($edge !== null) {
                $edgesByParent[$edge['parent']][] = $edge;
            }
        }

        $ordered = [];

        foreach ($requirements->groupBy(fn (Requirement $requirement): int => $requirement->parent_id ?? 0) as $parentId => $siblings) {
            [$void, $live] = $siblings->keyBy('id')->partition(fn (Requirement $requirement): bool => $requirement->status === RequirementStatus::Void);
            $liveEdges = array_filter($edgesByParent[$parentId] ?? [], fn (array $edge): bool => $live->has($edge['first']) && $live->has($edge['then']));
            $ordered[$parentId] = [...self::sorted($live, array_values($liveEdges)), ...self::sorted($void, [])];
        }

        return $ordered;
    }

    /**
     * @param  array<int, int>  $parentById  id => parent id (0 for roots)
     * @return list<int> ids from the root down to $id; empty when $id is not in this project
     */
    private static function path(array $parentById, int $id): array
    {
        $path = [];

        for ($cursor = $id; isset($parentById[$cursor]) && ! in_array($cursor, $path, true); $cursor = $parentById[$cursor]) {
            array_unshift($path, $cursor);
        }

        return $path;
    }

    /**
     * The sibling pair below the deepest common ancestor; null when one node contains the other.
     *
     * @param  list<int>  $dependentPath
     * @param  list<int>  $prerequisitePath
     * @return array{parent: int, first: int, then: int, isDirect: bool}|null
     */
    private static function liftedEdge(array $dependentPath, array $prerequisitePath): ?array
    {
        $depth = 0;

        while (isset($dependentPath[$depth], $prerequisitePath[$depth]) && $dependentPath[$depth] === $prerequisitePath[$depth]) {
            $depth++;
        }

        if (! isset($dependentPath[$depth], $prerequisitePath[$depth])) {
            return null;
        }

        return [
            'parent' => $depth === 0 ? 0 : $dependentPath[$depth - 1],
            'first' => $prerequisitePath[$depth],
            'then' => $dependentPath[$depth],
            'isDirect' => count($dependentPath) === $depth + 1 && count($prerequisitePath) === $depth + 1,
        ];
    }

    /**
     * Direct edges go in first, so a skipped loop always drops a lifted edge rather than a recorded dependency.
     *
     * @param  Collection<int, Requirement>  $siblings
     * @param  list<array{parent: int, first: int, then: int, isDirect: bool}>  $edges
     * @return list<Requirement>
     */
    private static function sorted(Collection $siblings, array $edges): array
    {
        usort($edges, fn (array $a, array $b): int => $b['isDirect'] <=> $a['isDirect']);
        $after = [];
        $waiting = [];

        foreach ($edges as ['first' => $first, 'then' => $then]) {
            if (isset($after[$first][$then]) || self::reaches($after, $then, $first)) {
                continue;
            }

            $after[$first][$then] = true;
            $waiting[$then] = ($waiting[$then] ?? 0) + 1;
        }

        $byId = $siblings->keyBy('id')->all();
        $ready = $siblings->filter(fn (Requirement $requirement): bool => ($waiting[$requirement->id] ?? 0) === 0)->values()->all();
        $ordered = [];

        while ($ready !== []) {
            usort($ready, fn (Requirement $a, Requirement $b): int => [$a->position ?? PHP_INT_MAX, $a->number, $a->id] <=> [$b->position ?? PHP_INT_MAX, $b->number, $b->id]);
            $next = array_shift($ready);
            $ordered[] = $next;

            foreach (array_keys($after[$next->id] ?? []) as $thenId) {
                if (--$waiting[$thenId] === 0) {
                    $ready[] = $byId[$thenId];
                }
            }
        }

        return $ordered;
    }

    /**
     * @param  array<int, array<int, true>>  $after
     */
    private static function reaches(array $after, int $from, int $to): bool
    {
        $stack = [$from];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $to) {
                return true;
            }

            if (! isset($seen[$current])) {
                $seen[$current] = true;
                array_push($stack, ...array_keys($after[$current] ?? []));
            }
        }

        return false;
    }
}
