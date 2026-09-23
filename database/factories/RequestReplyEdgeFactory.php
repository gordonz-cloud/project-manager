<?php

namespace Database\Factories;

use App\Enums\RequestReplyEdgeKind;
use App\Models\RequestReplyEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass both ends: they must share a use case.
 *
 * @extends Factory<RequestReplyEdge>
 */
class RequestReplyEdgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => RequestReplyEdgeKind::Next,
        ];
    }
}
