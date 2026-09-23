<?php

namespace Database\Factories;

use App\Enums\UseCaseStatus;
use App\Models\Module;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UseCase>
 */
class UseCaseFactory extends Factory
{
    public function forModule(Module $module): static
    {
        return $this->state(fn (): array => [
            'use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $module->project_id]),
            'project_id' => $module->project_id,
        ])->afterCreating(fn (UseCase $useCase) => $useCase->modules()->attach($module));
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'use_case_group_id' => UseCaseGroup::factory(),
            'project_id' => fn (array $attributes): int => UseCaseGroup::withoutGlobalScopes()
                ->whereKey($attributes['use_case_group_id'])->firstOrFail()
                ->project_id,
            'requirement_id' => null,
            'actor' => fake()->randomElement(['客户', '客服', '系统']),
            'goal' => fake()->sentence(),
            'trigger' => fake()->sentence(),
            'precondition' => fake()->optional()->sentence(),
            'success_outcome' => fake()->sentence(),
            'failure_outcome' => fake()->optional()->sentence(),
            'status' => UseCaseStatus::Draft,
        ];
    }
}
