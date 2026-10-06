<?php

namespace App\Models;

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
 * @property string|null $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'parent_id', 'kind', 'title', 'rationale', 'source', 'decided_by', 'decided_at', 'supersedes_id', 'acceptance', 'status', 'version'])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    /** Why the statement or status changed; written into the revision of the next save, then cleared. */
    public ?string $revisionReason = null;

    protected static function booted(): void
    {
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
            'decided_at' => 'date',
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
        $position = Module::inBuildOrder($project)->pluck('id')->flip(); // module id => build position

        $requirements = static::query()->where('project_id', $project->id)->with(['modules', 'dependsOn:id'])->get();
        $ids = $requirements->pluck('id')->flip();
        $modulePosition = $requirements->mapWithKeys(fn (Requirement $requirement) => [
            $requirement->id => $requirement->modules->max(fn (Module $m) => $position[$m->id] ?? -1) ?? -1,
        ])->all();

        /** @var array<int, int> $remaining number of un-placed dependencies per requirement id */
        $remaining = $requirements->mapWithKeys(fn (Requirement $requirement) => [
            $requirement->id => $requirement->dependsOn->filter(fn (Requirement $d) => isset($ids[$d->id]))->count(),
        ])->all();

        $ordered = new Collection;

        while ($ordered->count() < $requirements->count()) {
            $ready = $requirements
                ->reject(fn (Requirement $requirement) => $ordered->contains('id', $requirement->id))
                ->filter(fn (Requirement $requirement) => $remaining[$requirement->id] === 0)
                ->sortBy([
                    fn (Requirement $a, Requirement $b) => $modulePosition[$a->id] <=> $modulePosition[$b->id],
                    fn (Requirement $a, Requirement $b) => $a->id <=> $b->id,
                ]);

            if ($ready->isEmpty()) {
                // A cycle slipped past validation; stop rather than loop forever.
                break;
            }

            $next = $ready->first();
            $ordered->push($next);

            foreach ($requirements as $candidate) {
                if ($candidate->dependsOn->contains('id', $next->id)) {
                    $remaining[$candidate->id]--;
                }
            }
        }

        return $ordered;
    }
}
