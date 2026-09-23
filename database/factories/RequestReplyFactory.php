<?php

namespace Database\Factories;

use App\Enums\FeatureTrigger;
use App\Models\RequestReply;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestReply>
 */
class RequestReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'use_case_id' => UseCase::factory(),
            'trigger' => FeatureTrigger::Http,
            'method' => 'POST',
            'entry' => '/'.fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
        ];
    }
}
