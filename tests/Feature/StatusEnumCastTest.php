<?php

use App\Enums\FeatureLayer;
use App\Enums\RequirementStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;

test('status enum casts stored chinese value back to enum instance', function () {
    $project = Project::factory()->create();

    $requirement = Requirement::factory()->create([
        'project_id' => $project->id,
        'status' => RequirementStatus::InProgress,
    ]);

    $fresh = Requirement::find($requirement->id);

    expect($fresh->status)->toBeInstanceOf(RequirementStatus::class)
        ->and($fresh->status)->toBe(RequirementStatus::InProgress)
        ->and($fresh->getRawOriginal('status'))->toBe('进行中');
});

test('feature layer casts stored chinese value back to enum instance, and version stays nullable', function () {
    $project = Project::factory()->create();

    $feature = Feature::factory()->create([
        'project_id' => $project->id,
        'layer' => FeatureLayer::Backend,
        'version' => null,
    ]);

    $fresh = Feature::find($feature->id);

    expect($fresh->layer)->toBeInstanceOf(FeatureLayer::class)
        ->and($fresh->layer)->toBe(FeatureLayer::Backend)
        ->and($fresh->getRawOriginal('layer'))->toBe('后端')
        ->and($fresh->version)->toBeNull();
});
