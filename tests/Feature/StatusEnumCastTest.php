<?php

use App\Enums\RequirementStatus;
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
