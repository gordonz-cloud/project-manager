<?php

use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\Requirement;

test('status enum casts stored chinese value back to enum instance, and version stays nullable', function () {
    $project = Project::factory()->create();

    $requirement = Requirement::factory()->create([
        'project_id' => $project->id,
        'status' => RequirementStatus::Conflict,
        'version' => null,
    ]);

    $fresh = Requirement::find($requirement->id);

    expect($fresh->status)->toBeInstanceOf(RequirementStatus::class)
        ->and($fresh->status)->toBe(RequirementStatus::Conflict)
        ->and($fresh->getRawOriginal('status'))->toBe('冲突')
        ->and($fresh->version)->toBeNull();
});
