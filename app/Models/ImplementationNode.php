<?php

namespace App\Models;

use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\ImplementationNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int $feature_id
 * @property int|null $parent_id
 * @property int|null $module_id
 * @property string|null $file
 * @property string|null $function
 * @property string|null $input
 * @property string|null $change
 * @property string|null $output
 * @property ImplementationNodeKind $kind
 * @property string $title
 * @property string $contract
 * @property ImplementationNodeState $state
 * @property string $evidence_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['feature_id', 'parent_id', 'module_id', 'kind', 'title', 'contract', 'state', 'evidence_required', 'file', 'function', 'input', 'change', 'output'])]
class ImplementationNode extends Model
{
    /** @use HasFactory<ImplementationNodeFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $node): void {
            if ($node->exists && $node->isDirty('feature_id') && $node->children()->exists()) {
                throw new LogicException('An implementation node with children cannot change feature.');
            }

            if ($node->parent_id === null) {
                return;
            }

            $parent = self::withoutGlobalScopes()->find($node->parent_id);

            if ($parent === null || $parent->feature_id !== $node->feature_id) {
                throw new LogicException('An implementation node parent must belong to the same feature.');
            }

            if ($node->exists && self::wouldCycle($node->id, (int) $node->parent_id)) {
                throw new LogicException('An implementation node cannot be its own ancestor.');
            }
        });

        static::deleting(function (self $node): void {
            $ids = [$node->id, ...self::descendantIds($node->id)];

            if (NodeRun::withoutGlobalScopes()->whereIn('implementation_node_id', $ids)->exists()) {
                throw new LogicException('An implementation node with run history cannot be deleted.');
            }
        });
    }

    public static function wouldCycle(int $nodeId, int $parentId): bool
    {
        $visited = [];
        $current = $parentId;

        while ($current !== 0) {
            if ($current === $nodeId) {
                return true;
            }

            if (isset($visited[$current])) {
                return false;
            }

            $visited[$current] = true;
            $current = (int) (self::withoutGlobalScopes()->whereKey($current)->value('parent_id') ?? 0);
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public static function descendantIds(int $nodeId): array
    {
        $ids = [];
        $stack = [$nodeId];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach (self::withoutGlobalScopes()->where('parent_id', $current)->pluck('id') as $childId) {
                $childId = (int) $childId;
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public static function ancestorIds(int $nodeId): array
    {
        $ids = [];
        $current = (int) (self::withoutGlobalScopes()->whereKey($nodeId)->value('parent_id') ?? 0);

        while ($current !== 0 && ! in_array($current, $ids, true)) {
            $ids[] = $current;
            $current = (int) (self::withoutGlobalScopes()->whereKey($current)->value('parent_id') ?? 0);
        }

        return $ids;
    }

    /**
     * @param  Builder<ImplementationNode>  $query
     * @return Builder<ImplementationNode>
     */
    #[Scope]
    protected function forFeature(Builder $query, ?int $featureId): Builder
    {
        return $query->where('feature_id', $featureId);
    }

    public function hasRunHistory(): bool
    {
        return NodeRun::withoutGlobalScopes()
            ->whereIn('implementation_node_id', [$this->id, ...self::descendantIds($this->id)])
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ImplementationNodeKind::class,
            'state' => ImplementationNodeState::class,
        ];
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ImplementationNode, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ImplementationNodeEdge, $this>
     */
    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(ImplementationNodeEdge::class, 'from_node_id');
    }

    /**
     * @return HasMany<ImplementationNodeEdge, $this>
     */
    public function incomingEdges(): HasMany
    {
        return $this->hasMany(ImplementationNodeEdge::class, 'to_node_id');
    }

    /**
     * @return BelongsToMany<Scenario, $this>
     */
    public function scenarios(): BelongsToMany
    {
        return $this->belongsToMany(Scenario::class, 'scenario_implementation_nodes');
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<Scenario, $this>
     */
    public function endingScenarios(): HasMany
    {
        return $this->hasMany(Scenario::class, 'end_node_id');
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /**
     * @return HasMany<NodeRun, $this>
     */
    public function nodeRuns(): HasMany
    {
        return $this->hasMany(NodeRun::class);
    }
}
