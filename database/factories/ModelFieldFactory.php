<?php

namespace Database\Factories;

use App\Enums\DataModelStatus;
use App\Models\DataModel;
use App\Models\ModelField;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelField>
 */
class ModelFieldFactory extends Factory
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
            'data_model_id' => DataModel::factory(),
            'name' => fake()->word(),
            'type' => 'string',
            'status' => fake()->randomElement(DataModelStatus::cases()),
        ];
    }
}
