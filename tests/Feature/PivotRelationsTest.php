<?php

use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;

test('rules hang commits many-to-many, readable from both sides', function () {
    $project = Project::factory()->create();
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);
    $commit = Commit::factory()->create(['project_id' => $project->id]);

    $requirement->commits()->attach($commit);

    expect($requirement->commits()->first()->is($commit))->toBeTrue()
        ->and($commit->requirements()->first()->is($requirement))->toBeTrue();
});
