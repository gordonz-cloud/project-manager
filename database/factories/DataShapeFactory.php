<?php

namespace Database\Factories;

use App\Models\DataShape;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataShape>
 */
class DataShapeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->word(),
            'sample' => fake()->sentence(),
        ];
    }
}
