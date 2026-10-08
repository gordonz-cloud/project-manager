<?php

use App\Models\DataModel;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;

test('a model derives its modules from features → requirement → modules [T13]', function () {
    $project = Project::factory()->create();

    $module = Module::factory()->create(['project_id' => $project->id]);
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);
    $requirement->modules()->attach($module);

    $dataModel = DataModel::factory()->create(['project_id' => $project->id]);
    $featureOne = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $requirement->id]);
    $featureTwo = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $requirement->id]);
    $dataModel->features()->attach([$featureOne->id, $featureTwo->id]);

    $derived = $dataModel->derivedModules();

    expect($derived)->toHaveCount(1)
        ->and($derived->first()->is($module))->toBeTrue();
});
