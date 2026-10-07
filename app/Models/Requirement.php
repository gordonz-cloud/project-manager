<?php

namespace App\Models;

use App\Data\Requirements\RequirementDecision;
use App\Enums\RequirementDecider;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToProject;
use App\Models\Concerns\HasProjectSequence;
use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

/**
 * One node of the requirement tree: a statement that should hold (title), why (rationale), where it came from (source)
 * and who decided it. Every change of statement or decision status leaves a RequirementRevision.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $number
 * @property int|null $parent_id
 * @property RequirementKind|null $kind
 * @property string $title
 * @property string|null $rationale
 * @property string|null $source
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $supersedes_id
 * @property string|null $acceptance
 * @property RequirementStatus $status
 * @property RequirementDecider|null $decider
 * @property string|null $version
 * @property int|null $position
 * @property RequirementDecision|null $decision
 * @property array{key: string, label: string, by: string, at: string}|null $decision_opinion Gordon's opinion on a question for 老板
 * @property Carbon|null $sent_to_boss_at when the question went to 老板
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'parent_id', 'kind', 'title', 'rationale', 'source', 'decided_by', 'decided_at', 'supersedes_id', 'acceptance', 'status', 'decider', 'version', 'position', 'decision', 'decision_opinion', 'sent_to_boss_at'])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    /** Waits on Gordon: his to decide, or 老板's but Gordon has not given his opinion yet. */
    public const STAGE_MINE = 'me';

    /** 老板's, with Gordon's opinion, not sent yet. */
    public const STAGE_TO_SEND = 'to_send';

    /** Sent to 老板, waiting for the reply. */
    public const STAGE_AWAITING_BOSS = 'awaiting_boss';

    /** Why the statement or status changed; written into the revision of the next save, then cleared. */
    public ?string $revisionReason = null;

    protected static function booted(): void
    {
        static::saving(function (self $requirement): void {
            if (! $requirement->status->awaitsDecision()) {
                $requirement->decider = null;
            }
        });

        static::created(function (self $requirement): void {
            $requirement->recordRevision(isNew: true);
        });

        static::updated(function (self $requirement): void {
            if ($requirement->wasChanged(['title', 'status'])) {
                $requirement->recordRevision(isNew: false);
            }
        });

        static::deleting(function (self $requirement): void {
            if (WorkflowRun::withoutGlobalScopes()->where('requirement_id', $requirement->id)->exists()) {
                throw new LogicException('A requirement with workflow history cannot be deleted.');
            }

        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequirementStatus::class,
            'kind' => RequirementKind::class,
            'decider' => RequirementDecider::class,
            'decided_at' => 'date',
            'decision' => RequirementDecision::class,
            'decision_opinion' => 'array',
            'sent_to_boss_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Requirement, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The existing rule this proposal would replace.
     *
     * @return BelongsTo<Requirement, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * The decided rule that replaced this one (it is 作废 because of it).
     *
     * @return HasOne<Requirement, $this>
     */
    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id')->where('status', RequirementStatus::Decided);
    }

    /**
     * Features that make this statement hold (many-to-many; features.requirement_id is mirrored into it).
     *
     * @return BelongsToMany<Feature, $this>
     */
    public function linkedFeatures(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    /**
     * Test-tree nodes that verify this statement directly.
     *
     * @return BelongsToMany<Test, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class);
    }

    /**
     * Where an open decision stands (one of the STAGE_ constants); null once it is decided or voided.
     */
    public function decisionStage(): ?string
    {
        return match (true) {
            ! $this->status->awaitsDecision() => null,
            $this->decider !== RequirementDecider::Boss || $this->decision_opinion === null => self::STAGE_MINE,
            $this->sent_to_boss_at === null => self::STAGE_TO_SEND,
            default => self::STAGE_AWAITING_BOSS,
        };
    }

    /**
     * What Gordon is asked: the written decision, or the plain two-way choice when none was written.
     */
    public function decisionOrFallback(): RequirementDecision
    {
        return $this->decision ?? RequirementDecision::fallbackFor($this);
    }

    /**
     * @return HasMany<RequirementRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(RequirementRevision::class)->latest('id');
    }

    /**
     * Newest commits of the linked features.
     *
     * @return EloquentCollection<int, Commit>
     */
    public function recentCommits(int $limit = 10): EloquentCollection
    {
        return Commit::withoutGlobalScopes()
            ->whereIn('feature_id', $this->linkedFeatures()->select('features.id'))
            ->with('feature:id,number,title')
            ->latest('committed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Root first, ending with the parent.
     *
     * @return list<Requirement>
     */
    public function ancestors(): array
    {
        $path = [];

        for ($node = $this->parent; $node !== null; $node = $node->parent) {
            array_unshift($path, $node);
        }

        return $path;
    }

    /**
     * New rows and changes of statement or decision status each leave one revision.
     */
    private function recordRevision(bool $isNew): void
    {
        $oldStatus = $isNew ? null : $this->getOriginal('status');

        $this->revisions()->create([
            'old_statement' => $isNew ? null : $this->getOriginal('title'),
            'new_statement' => $this->title,
            'old_status' => $oldStatus instanceof RequirementStatus ? $oldStatus->value : $oldStatus,
            'new_status' => $this->status->value,
            'reason' => $this->revisionReason,
            'source' => $this->source,
            'decided_by' => $this->decided_by,
        ]);

        $this->revisionReason = null;
    }

    /**
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }

    /**
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /**
     * @return HasMany<UseCase, $this>
     */
    public function useCases(): HasMany
    {
        return $this->hasMany(UseCase::class);
    }

    public function hasWorkflowHistory(): bool
    {
        return WorkflowRun::withoutGlobalScopes()
            ->where('requirement_id', $this->id)
            ->exists();
    }

    /**
     * Requirements this requirement depends on (must be built first).
     *
     * @return BelongsToMany<Requirement, $this>
     */
    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'requirement_dependencies', 'requirement_id', 'depends_on_requirement_id');
    }

    /**
     * Requirements that depend on this requirement.
     *
     * @return BelongsToMany<Requirement, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'requirement_dependencies', 'depends_on_requirement_id', 'requirement_id');
    }

    /**
     * Whether making $requirementId depend on $dependsOnId would create a cycle,
     * i.e. $dependsOnId already (directly or transitively) depends on $requirementId.
     */
    public static function wouldCycle(int $requirementId, int $dependsOnId): bool
    {
        if ($requirementId === $dependsOnId) {
            return true;
        }

        $visited = [];
        $stack = [$dependsOnId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $requirementId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            $stack = array_merge($stack, static::find((int) $current)?->dependsOn()->pluck('requirements.id')->all() ?? []);
        }

        return false;
    }

    /**
     * Build order (Kahn's algorithm): a requirement never precedes anything it
     * depends on. Among the ready ones, the furthest-built module goes first
     * (a requirement touching several modules sorts by whichever module is
     * built last), then the lower id. This is the pickup order for /feature-run.
     *
     * @return Collection<int, Requirement>
     */
    public static function inBuildOrder(Project $project): Collection
    {
        /** @var \ArrayObject<int, Collection<int, Requirement>> $memo */
        $memo = once(fn () => new \ArrayObject);

        return $memo[$project->id] ??= self::computeBuildOrder($project);
    }

    /**
     * @param  Builder<Requirement>  $query
     * @return Builder<Requirement>
     */
    #[Scope]
    protected function withVersion(Builder $query): Builder
    {
        return $query->whereNotNull('version');
    }

    /**
     * @return Collection<int, Requirement>
     */
    private static function computeBuildOrder(Project $project): Collection
    {
        $position = array_flip(Module::inBuildOrder($project)->pluck('id')->all()); // module id => build position

        $requirements = static::query()->where('project_id', $project->id)->with(['modules', 'dependsOn:id'])->get();
        $ids = $requirements->pluck('id')->flip();
        $modulePosition = $requirements->mapWithKeys(fn (Requirement $requirement) => [
            $requirement->id => $requirement->modules->max(fn (Module $m): int => $position[$m->id] ?? -1) ?? -1,
        ])->all();

        /** @var array<int, int> $remaining number of un-placed dependencies per requirement id */
        $remaining = $requirements->mapWithKeys(fn (Requirement $requirement) => [
            $requirement->id => $requirement->dependsOn->filter(fn (Requirement $d) => isset($ids[$d->id]))->count(),
        ])->all();

        /** @var array<int, list<int>> $dependents requirement id => ids that depend on it */
        $dependents = [];
        foreach ($requirements as $requirement) {
            foreach ($requirement->dependsOn as $dependency) {
                if (isset($ids[$dependency->id])) {
                    $dependents[$dependency->id][] = $requirement->id;
                }
            }
        }

        // Earliest module first, then lowest id: same tie-break as before, in O((n + e) log n).
        $queue = new \SplPriorityQueue;
        $enqueue = fn (int $id) => $queue->insert($id, [-$modulePosition[$id], -$id]);
        foreach ($remaining as $id => $count) {
            if ($count === 0) {
                $enqueue($id);
            }
        }

        $byId = $requirements->keyBy('id');
        $ordered = new Collection;

        // A cycle that slipped past validation simply leaves its members out.
        while (! $queue->isEmpty()) {
            $id = $queue->extract();
            $ordered->push($byId[$id]);

            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$remaining[$dependent] === 0) {
                    $enqueue($dependent);
                }
            }
        }

        return $ordered;
    }
}
