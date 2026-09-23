<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasOne<ModuleSpec, $this>
     */
    public function spec(): HasOne
    {
        return $this->hasOne(ModuleSpec::class);
    }

    /**
     * @return BelongsToMany<UseCase, $this>
     */
    public function useCases(): BelongsToMany
    {
        return $this->belongsToMany(UseCase::class, 'module_use_cases');
    }

    /**
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class);
    }

    /**
     * Modules this module depends on (must be built first).
     *
     * @return BelongsToMany<Module, $this>
     */
    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_dependencies', 'module_id', 'depends_on_module_id');
    }

    /**
     * Modules that depend on this module.
     *
     * @return BelongsToMany<Module, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_dependencies', 'depends_on_module_id', 'module_id');
    }

    /**
     * Whether making $moduleId depend on $dependsOnId would create a cycle,
     * i.e. $dependsOnId already (directly or transitively) depends on $moduleId.
     */
    public static function wouldCycle(int $moduleId, int $dependsOnId): bool
    {
        if ($moduleId === $dependsOnId) {
            return true;
        }

        $visited = [];
        $stack = [$dependsOnId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $moduleId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            $stack = array_merge($stack, static::find((int) $current)?->dependsOn()->pluck('modules.id')->all() ?? []);
        }

        return false;
    }

    /**
     * Topological build order (Kahn's algorithm): a module never precedes
     * anything it depends on. Ties broken by name.
     *
     * @return Collection<int, Module>
     */
    public static function inBuildOrder(Project $project): Collection
    {
        /** @var \ArrayObject<int, Collection<int, Module>> $memo */
        $memo = once(fn () => new \ArrayObject);

        return $memo[$project->id] ??= self::computeBuildOrder($project);
    }

    /**
     * @return Collection<int, Module>
     */
    private static function computeBuildOrder(Project $project): Collection
    {
        $modules = static::query()->where('project_id', $project->id)->with('dependsOn:id')->get()->sortBy('name')->values();

        /** @var array<int, int> $remaining number of un-placed dependencies per module id */
        $remaining = $modules->mapWithKeys(fn (Module $module) => [$module->id => $module->dependsOn->count()])->all();

        $ordered = new Collection;

        while ($ordered->count() < $modules->count()) {
            $ready = $modules
                ->reject(fn (Module $module) => $ordered->contains('id', $module->id))
                ->filter(fn (Module $module) => $remaining[$module->id] === 0)
                ->sortBy('name');

            if ($ready->isEmpty()) {
                // A cycle slipped past validation; stop rather than loop forever.
                break;
            }

            foreach ($ready as $module) {
                $ordered->push($module);

                foreach ($modules as $candidate) {
                    if ($ordered->contains('id', $candidate->id)) {
                        continue;
                    }

                    if ($candidate->dependsOn->contains('id', $module->id)) {
                        $remaining[$candidate->id]--;
                    }
                }
            }
        }

        return $ordered;
    }
}
