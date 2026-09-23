<?php

namespace Database\Factories;

use App\Models\UseCase;
use App\Models\UseCaseSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UseCaseSpec>
 */
class UseCaseSpecFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'use_case_id' => UseCase::factory(),
            'version' => 1,
            'status' => 'draft',
            'content' => fake()->paragraphs(2, true),
        ];
    }
}
