<?php

use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test as TestModel;

test('rules hang tests and commits many-to-many, readable from both sides', function () {
    $project = Project::factory()->create();
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);
    $test = TestModel::factory()->create(['project_id' => $project->id]);
    $commit = Commit::factory()->create(['project_id' => $project->id]);

    $requirement->tests()->attach($test);
    $requirement->commits()->attach($commit);

    expect($requirement->tests()->first()->is($test))->toBeTrue()
        ->and($test->requirements()->first()->is($requirement))->toBeTrue()
        ->and($requirement->commits()->first()->is($commit))->toBeTrue()
        ->and($commit->requirements()->first()->is($requirement))->toBeTrue();
});
