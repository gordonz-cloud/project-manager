<?php

namespace App\Services\Tests;

use App\Data\Tests\TestArea;
use App\Data\Tests\TestTreeNode;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Enums\TestLastResult;
use App\Models\Project;
use App\Models\Requirement;
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
     * Decided rules no test node verifies.
     *
     * @return Collection<int, Requirement>
     */
    public function uncoveredRules(Project $project): Collection
    {
        return Requirement::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('kind', RequirementKind::Rule)
            ->where('status', RequirementStatus::Decided)
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
