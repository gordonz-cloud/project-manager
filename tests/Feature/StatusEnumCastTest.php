<?php

use App\Enums\FeatureLayer;
use App\Enums\RequirementStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;

test('status enum casts stored chinese value back to enum instance, and version stays nullable', function () {
    $project = Project::factory()->create();

    $requirement = Requirement::factory()->create([
        'project_id' => $project->id,
        'status' => RequirementStatus::InProgress,
        'version' => null,
    ]);

    $fresh = Requirement::find($requirement->id);

    expect($fresh->status)->toBeInstanceOf(RequirementStatus::class)
        ->and($fresh->status)->toBe(RequirementStatus::InProgress)
        ->and($fresh->getRawOriginal('status'))->toBe('进行中')
        ->and($fresh->version)->toBeNull();
});

test('feature layers casts stored chinese values back as an array', function () {
    $project = Project::factory()->create();

    $feature = Feature::factory()->create([
        'project_id' => $project->id,
        'layers' => [FeatureLayer::Backend->value, FeatureLayer::Frontend->value],
    ]);

    $fresh = Feature::find($feature->id);

    expect($fresh->layers)->toBe(['后端', '前端'])
        ->and(json_decode((string) $fresh->getRawOriginal('layers'), true))->toBe(['后端', '前端']);
});
