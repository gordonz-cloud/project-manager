<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\Flowchart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flowchart>
 */
class FlowchartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature_id' => Feature::factory(),
            'chart' => [
                'nodes' => [
                    ['id' => 'n1', 'label' => '开始', 'shape' => 'start'],
                    ['id' => 'n2', 'label' => '结束', 'shape' => 'end'],
                ],
                'edges' => [['from' => 'n1', 'to' => 'n2']],
            ],
            'pseudocode' => "1. 开始\n2. 结束",
        ];
    }
}
