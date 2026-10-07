<?php

namespace App\Services\Requirements;

use App\Data\Requirements\RequirementChangeGroup;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads a project's requirement tree, the decisions waiting on Gordon, and what changed lately.
 */
class RequirementTreeService
{
    /**
     * Every level in RequirementSiblingOrder: depended-on first, then position, then number.
     *
     * @return list<RequirementTreeNode>
     */
    public function tree(Project $project): array
    {
        $requirements = Requirement::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->with(['linkedFeatures:id,status', 'tests:id,last_result', 'supersededBy:id,number,title,supersedes_id,decided_at'])
            ->get();
        $dependencies = DB::table('requirement_dependencies')
            ->whereIn('requirement_id', Requirement::withoutGlobalScopes()->where('project_id', $project->id)->select('id'))
            ->get()
            ->map(fn (object $row): array => [(int) $row->requirement_id, (int) $row->depends_on_requirement_id]);

        return $this->branch(RequirementSiblingOrder::childrenByParent($requirements, $dependencies), 0);
    }

    /**
     * Every 提议 and 冲突 except 分组 (only a way of filing rules) with what a decision would touch: 冲突 first (they change a rule already built), then in tree order.
     *
     * @param  list<RequirementTreeNode>  $tree  this project's tree()
     * @return Collection<int, Requirement>
     */
    public function awaitingDecision(Project $project, array $tree): Collection
    {
        $treeOrder = array_flip(array_map(fn (RequirementTreeNode $node): int => $node->requirement->id, RequirementTreeNode::flattened($tree)));

        return Requirement::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->whereIn('status', [RequirementStatus::Proposed, RequirementStatus::Conflict])
            ->where(fn (Builder $query) => $query->whereNull('kind')->orWhere('kind', '!=', RequirementKind::Group))
            ->with(['linkedFeatures', 'tests', 'supersedes.linkedFeatures', 'supersedes.tests'])
            ->get()
            ->sortBy(fn (Requirement $requirement): array => [$requirement->status === RequirementStatus::Conflict ? 0 : 1, $treeOrder[$requirement->id] ?? PHP_INT_MAX])
            ->values();
    }

    /**
     * Decided rules that are not built yet, in the order to build them: the tree's order, where every level puts what
     * is depended on first. 进行中 ones stay in the list so nobody starts the next before finishing them.
     *
     * @param  list<RequirementTreeNode>  $tree
     * @return list<RequirementTreeNode>
     */
    public function todo(array $tree): array
    {
        return array_values(array_filter(
            RequirementTreeNode::flattened($tree),
            fn (RequirementTreeNode $node): bool => $node->children === [] && in_array($node->progress, [RequirementProgress::Todo, RequirementProgress::InProgress], true),
        ));
    }

    /**
     * Revisions and commits of the last $limit of each, grouped by requirement (at most 10 commits a group), most recently touched first.
     *
     * @return list<RequirementChangeGroup>
     */
    public function recentChanges(Project $project, int $limit = 200): array
    {
        $revisions = RequirementRevision::query()
            ->whereIn('requirement_id', Requirement::withoutGlobalScopes()->where('project_id', $project->id)->select('id'))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->groupBy('requirement_id');

        $commits = Commit::withoutGlobalScopes()
            ->where('commits.project_id', $project->id)
            ->join('feature_requirement', 'feature_requirement.feature_id', '=', 'commits.feature_id')
            ->select('commits.*', 'feature_requirement.requirement_id as requirement_id')
            ->with('feature:id,number,title')
            ->latest('committed_at')
            ->limit($limit)
            ->get()
            ->groupBy('requirement_id');

        $requirements = Requirement::withoutGlobalScopes()
            ->whereKey($revisions->keys()->merge($commits->keys())->unique()->all())
            ->get();

        return array_values($requirements
            ->map(fn (Requirement $requirement): RequirementChangeGroup => new RequirementChangeGroup(
                $requirement,
                $revisions->get($requirement->id, collect()),
                $commits->get($requirement->id, collect())->take(10),
            ))
            ->sortByDesc(fn (RequirementChangeGroup $group): int => $group->latestAt()?->getTimestamp() ?? 0)
            ->all());
    }

    /**
     * @param  array<int, list<Requirement>>  $childrenByParent
     * @return list<RequirementTreeNode>
     */
    private function branch(array $childrenByParent, int $parentId): array
    {
        $nodes = [];

        foreach ($childrenByParent[$parentId] ?? [] as $requirement) {
            $nodes[] = RequirementTreeNode::build($requirement, $this->branch($childrenByParent, $requirement->id));
        }

        return $nodes;
    }
}
