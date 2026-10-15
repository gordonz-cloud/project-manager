<?php

namespace App\Services\Requirements;

use App\Data\Requirements\RequirementChange;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementRevision;
use Carbon\CarbonInterface;
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
            ->with('supersededBy:id,number,title,supersedes_id,decided_at')
            ->withDeliveryCounts()
            ->get();
        $dependencies = DB::table('requirement_dependencies')
            ->whereIn('requirement_id', Requirement::withoutGlobalScopes()->where('project_id', $project->id)->select('id'))
            ->orderBy('requirement_id')
            ->orderBy('depends_on_requirement_id')
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
            ->with(['commits', 'tests', 'supersedes.commits', 'supersedes.tests'])
            ->get()
            ->sortBy(fn (Requirement $requirement): array => [$requirement->status === RequirementStatus::Conflict ? 0 : 1, $treeOrder[$requirement->id] ?? PHP_INT_MAX])
            ->values();
    }

    /**
     * Leaves showing $progress, in tree order; for 待做 that is the order to build them, as every level puts what is
     * depended on first.
     *
     * @param  list<RequirementTreeNode>  $tree
     * @return list<RequirementTreeNode>
     */
    public function leaves(array $tree, RequirementProgress $progress): array
    {
        return array_values(array_filter(
            RequirementTreeNode::flattened($tree),
            fn (RequirementTreeNode $node): bool => $node->children === [] && $node->progress === $progress,
        ));
    }

    /**
     * Everything that happened to this project's requirements since $from, newest first (later revision first within a
     * second): one query for the revisions and
     * one for requirements created without a 新 revision (older than revisions).
     *
     * @return list<RequirementChange>
     */
    public function changesSince(Project $project, CarbonInterface $from): array
    {
        $ids = Requirement::withoutGlobalScopes()->where('project_id', $project->id)->select('id');

        $revised = RequirementRevision::query()
            ->whereIn('requirement_id', $ids)
            ->where('created_at', '>=', $from)
            ->orderByDesc('id')
            ->get()
            ->map(fn (RequirementRevision $revision): RequirementChange => RequirementChange::fromRevision($revision));

        $createdUnrecorded = Requirement::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('created_at', '>=', $from)
            ->whereDoesntHave('revisions', fn (Builder $query) => $query->whereNull('old_status'))
            ->get(['id', 'created_at'])
            ->map(fn (Requirement $requirement): RequirementChange => RequirementChange::created($requirement->id, $requirement->created_at ?? $from));

        return array_values($revised->concat($createdUnrecorded)
            ->sortByDesc(fn (RequirementChange $change): int => $change->at->getTimestamp())
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
