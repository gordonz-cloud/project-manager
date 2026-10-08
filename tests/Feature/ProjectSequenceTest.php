<?php

use App\Models\DataModel;
use App\Models\Feature;
use App\Models\ModelField;
use App\Models\Project;

test('feature numbers increment per project [T9]', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    $first = Feature::factory()->create(['project_id' => $projectA->id]);
    $second = Feature::factory()->create(['project_id' => $projectA->id]);
    $other = Feature::factory()->create(['project_id' => $projectB->id]);

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($other->number)->toBe(1);
});

test('feature number is not overwritten when explicitly provided', function () {
    $project = Project::factory()->create();

    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 7]);

    expect($feature->number)->toBe(7);
});

test('model field numbers increment per project [T12]', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $dataModel = DataModel::factory()->create(['project_id' => $projectA->id]);

    $first = ModelField::factory()->create(['project_id' => $projectA->id, 'data_model_id' => $dataModel->id]);
    $second = ModelField::factory()->create(['project_id' => $projectA->id, 'data_model_id' => $dataModel->id]);
    $other = ModelField::factory()->create(['project_id' => $projectB->id, 'data_model_id' => $dataModel->id]);

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($other->number)->toBe(1);
});

test('model field number is not overwritten when explicitly provided', function () {
    $project = Project::factory()->create();
    $dataModel = DataModel::factory()->create(['project_id' => $project->id]);

    $field = ModelField::factory()->create(['project_id' => $project->id, 'data_model_id' => $dataModel->id, 'number' => 7]);

    expect($field->number)->toBe(7);
});
