<?php

namespace App\Services\Tests;

use App\Data\Tests\TestArea;
use App\Data\Tests\TestTreeNode;
use App\Enums\TestLastResult;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads a project's Test Matrix as a tree, built in PHP from one query.
 */
class TestTreeService
{
    /**
     * @return list<TestTreeNode>
     */
    public function tree(Project $project): array
    {
        $childrenByParent = Test::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->orderBy('number')
            ->get()
            ->groupBy(fn (Test $test): int => $test->parent_id ?? 0);

        return $this->branch($childrenByParent->all(), 0, false);
    }

    /**
     * The tree regrouped by business area.
     *
     * @return list<TestArea>
     */
    public function areas(Project $project): array
    {
        return TestArea::groupAll($this->tree($project));
    }

    /**
     * Features no test node is tagged with.
     *
     * @return Collection<int, Feature>
     */
    public function uncoveredFeatures(Project $project): Collection
    {
        return Feature::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->whereDoesntHave('tests')
            ->orderBy('number')
            ->get(['id', 'number', 'title']);
    }

    /**
     * @param  array<int, \Illuminate\Support\Collection<int, Test>>  $childrenByParent
     * @return list<TestTreeNode>
     */
    private function branch(array $childrenByParent, int $parentId, bool $hasFailedAncestor): array
    {
        $nodes = [];

        foreach ($childrenByParent[$parentId] ?? [] as $test) {
            $blocksBelow = $hasFailedAncestor || $test->last_result === TestLastResult::Failed;
            $nodes[] = TestTreeNode::build($test, $hasFailedAncestor, $this->branch($childrenByParent, $test->id, $blocksBelow));
        }

        return $nodes;
    }
}
