<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleSpec>
 */
class ModuleSpecFactory extends Factory
{
    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
            'module_id' => Module::factory()->state(['project_id' => $project->id]),
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'project_id' => fn (array $attributes): int => Module::withoutGlobalScopes()
                ->whereKey($attributes['module_id'])->firstOrFail()
                ->project_id,
            'version' => 1,
            'status' => 'draft',
            'summary' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
        ];
    }
}
