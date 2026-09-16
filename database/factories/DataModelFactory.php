<?php

namespace Database\Factories;

use App\Enums\DataModelStatus;
use App\Models\DataModel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataModel>
 */
class DataModelFactory extends Factory
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
            'status' => fake()->randomElement(DataModelStatus::cases()),
        ];
    }
}
