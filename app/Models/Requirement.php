<?php

namespace App\Models;

use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $acceptance
 * @property RequirementStatus $status
 * @property string|null $notion_url
 * @property string|null $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'acceptance', 'status', 'notion_url', 'version'])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequirementStatus::class,
        ];
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
     * @return Collection<int, static>
     */
    public static function inBuildOrder(Project $project): Collection
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
