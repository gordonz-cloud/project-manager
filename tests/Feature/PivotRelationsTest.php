<?php

use App\Models\DataModel;
use App\Models\Feature;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test as TestModel;

test('all seven many-to-many relations attach and read back', function () {
    $project = Project::factory()->create();

    $module = Module::factory()->create(['project_id' => $project->id]);
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);
    $dataModel = DataModel::factory()->create(['project_id' => $project->id]);
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    $modelField = ModelField::factory()->create(['project_id' => $project->id, 'data_model_id' => $dataModel->id]);
    $test = TestModel::factory()->create(['project_id' => $project->id]);

    $module->requirements()->attach($requirement);
    $dataModel->features()->attach($feature);
    $feature->tests()->attach($test);

    expect($module->requirements()->first()->is($requirement))->toBeTrue()
        ->and($requirement->modules()->first()->is($module))->toBeTrue()
        ->and($dataModel->features()->first()->is($feature))->toBeTrue()
        ->and($feature->dataModels()->first()->is($dataModel))->toBeTrue()
        ->and($feature->tests()->first()->is($test))->toBeTrue()
        ->and($test->features()->first()->is($feature))->toBeTrue();
});
