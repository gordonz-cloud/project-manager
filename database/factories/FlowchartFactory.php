<?php

namespace Database\Factories;

use App\Enums\RequirementKind;
use App\Models\Flowchart;
use App\Models\Requirement;
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
            'requirement_id' => Requirement::factory()->state(['kind' => RequirementKind::Rule]),
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
