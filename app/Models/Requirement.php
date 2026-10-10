<?php

namespace App\Models;

use App\Data\Requirements\RequirementDecision;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestStatus;
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
 * @property int|null $position
 * @property RequirementDecision|null $decision
 * @property list<array<string, mixed>>|null $timeline everything said about it over time (see RequirementTimeline)
 * @property bool $needs_review once its tests pass, Gordon still has to look (UI, copy) before it counts as done
 * @property Carbon|null $accepted_at when Gordon accepted it; an accepted rule is done unless a test fails, and a new statement or status needs accepting again
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'parent_id', 'kind', 'title', 'rationale', 'source', 'decided_by', 'decided_at', 'supersedes_id', 'acceptance', 'status', 'version', 'position', 'decision', 'timeline', 'needs_review', 'accepted_at'])]
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

        static::updating(function (self $requirement): void {
            if ($requirement->isDirty(['title', 'status'])) {
                $requirement->accepted_at = null;
            }
        });

        static::updated(function (self $requirement): void {
            if ($requirement->wasChanged(['title', 'status'])) {
                $requirement->recordRevision(isNew: false);
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
            'decision' => RequirementDecision::class,
            'timeline' => 'array',
            'needs_review' => 'boolean',
            'accepted_at' => 'datetime',
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
     * The rule's own flowchart: only the stretch of logic that makes this one rule hold.
     *
     * @return HasOne<Flowchart, $this>
     */
    public function flowchart(): HasOne
    {
        return $this->hasOne(Flowchart::class);
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
     * Commits that work on this statement (their message names R<number>).
     *
     * @return BelongsToMany<Commit, $this>
     */
    public function commits(): BelongsToMany
    {
        return $this->belongsToMany(Commit::class);
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
     * What the question is about, for a rule worded from Gordon's own answer: the title without its status prefix
     * (冲突：/待定：…), the question part (给不给…, 要不要…) and question marks, at most 16 Chinese characters wide.
     */
    public function topic(): string
    {
        $topic = (string) preg_replace('/^(?:冲突|待定|待老板定|待评估|第二版待评估|提议)[：:]\s*/u', '', $this->title);
        $topic = (string) preg_replace('/(?:给不给|要不要|能不能|是不是|是否|怎么|如何|谁来).*$/u', '', $topic);

        return mb_strimwidth(trim(str_replace(['？', '?'], '', $topic)), 0, 32);
    }

    /**
     * Where 现在 comes from: the replaced rule's source for a 冲突, else decision.now_source; null when no document says
     * it (it is just what the code does).
     */
    public function nowSource(): ?string
    {
        return self::briefSource($this->status === RequirementStatus::Conflict ? $this->supersedes?->source : null)
            ?? self::briefSource($this->decision?->nowSource);
    }

    /**
     * A source as the panel shows it: document, version and date, without file paths, section marks or code references
     * (the stored source keeps them for whoever traces it).
     */
    public static function briefSource(?string $source): ?string
    {
        $brief = (string) preg_replace(
            ['/现状（代码[^）]*）/u', '/\.ai\/rules\/[\w-]+\.md\s?§?/u', '/(?:app|resources|database|tests)\/\S+/u', '/docs\/product\/(?:_shared\/)?/u', '/([\w-]+)\/([\w-]+)\.md/u', '/([\w-]+)\.md/u',
                '/§\s?[\d.一二三四五六七八九十]+(?:\s?#\d+)?/u', '/第\s?\d+\s?项/u', '/验收\s?\d+/u', '/(?<![\w-])[A-RT-Z]\d+(?:-\d+)?(?![\w-])/u',
                '/（[^（）]*?(\d{4}-\d{2}-\d{2})）/u', '/\s*、(?:\s*、)+/u', '/、\s*(?=\d{4}-|）|；|$)/u', '/\s+([、，；）])/u', '/（\s*）/u', '/ {2,}/u'],
            ['代码现状', '项目规则 ', '', '', '$1 $2', '$1', '', '', '', '', ' $1', '、', '', '$1', '', ' '],
            (string) $source,
        );

        return trim($brief, ' 、，') ?: null;
    }

    /**
     * What Gordon is asked: the written decision, or the plain question when none was written.
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
     * Newest commits on this statement.
     *
     * @return EloquentCollection<int, Commit>
     */
    public function recentCommits(int $limit = 10): EloquentCollection
    {
        return $this->commits()->withoutGlobalScopes()->latest('committed_at')->limit($limit)->get();
    }

    /**
     * Gordon looked and it works; noted in its history.
     */
    public function accept(): void
    {
        $this->forceFill(['accepted_at' => now()])->save();
        $this->revisions()->create([
            'new_statement' => $this->title,
            'old_status' => $this->status->value,
            'new_status' => $this->status->value,
            'reason' => '验收通过',
            'decided_by' => 'Gordon',
        ]);
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
     * depends on. Among the ready ones, the lower id goes first.
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
     * What DeliveryStatus needs, counted in SQL instead of loading every linked test: its live tests (过时/停用 are no
     * evidence), how many failed, how many are automated and how many of those passed, and whether a commit works on it.
     *
     * @param  Builder<Requirement>  $query
     * @return Builder<Requirement>
     */
    #[Scope]
    protected function withDeliveryCounts(Builder $query): Builder
    {
        $live = fn (Builder $tests): Builder => $tests->whereIn('tests.status', [TestStatus::Valid, TestStatus::ToWrite]);
        $automated = fn (Builder $tests): Builder => $live($tests)->where(fn (Builder $q) => $q->whereNull('tests.auto')->orWhere('tests.auto', '!=', TestAuto::No));

        return $query->withExists('commits')->withCount([
            'tests as live_tests_count' => $live,
            'tests as failed_tests_count' => fn (Builder $tests): Builder => $live($tests)->where('tests.last_result', TestLastResult::Failed),
            'tests as automated_tests_count' => $automated,
            'tests as passed_automated_tests_count' => fn (Builder $tests): Builder => $automated($tests)->where('tests.last_result', TestLastResult::Passed),
            'tests as passed_tests_count' => fn (Builder $tests): Builder => $live($tests)->where('tests.last_result', TestLastResult::Passed),
        ]);
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
        $requirements = static::query()->where('project_id', $project->id)->with('dependsOn:id')->get();
        $ids = $requirements->pluck('id')->flip();

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

        // Lowest id first, in O((n + e) log n).
        $queue = new \SplPriorityQueue;
        $enqueue = fn (int $id) => $queue->insert($id, -$id);
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
