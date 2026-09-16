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
     * Requirements ordered by their modules' build position — a requirement
     * touching several modules sorts by whichever module is built last.
     * This is the pickup order for /feature-run.
     *
     * @return Collection<int, static>
     */
    public static function inBuildOrder(Project $project): Collection
    {
        $position = Module::inBuildOrder($project)->pluck('id')->flip(); // module id => build position

        return collect(
            static::query()->where('project_id', $project->id)->with('modules')->get()
                ->sortBy([
                    fn (Requirement $a, Requirement $b) => ($a->modules->max(fn (Module $m) => $position[$m->id] ?? -1) ?? -1)
                        <=> ($b->modules->max(fn (Module $m) => $position[$m->id] ?? -1) ?? -1),
                    fn (Requirement $a, Requirement $b) => $a->id <=> $b->id,
                ])
                ->values()
                ->all()
        );
    }
}
