<?php

namespace Database\Factories;

use App\Models\Commit;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commit>
 */
class CommitFactory extends Factory
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
            'hash' => fake()->sha1(),
            'subject' => fake()->sentence(4),
            'body' => null,
            'author' => fake()->name(),
            'committed_at' => fake()->dateTimeThisYear(),
        ];
    }
}
