<?php

use App\Filament\Resources\FlowSteps\Pages\CreateFlowStep;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the path field offers the paths already used by the selected feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $feature = Feature::factory()->create(['project_id' => $project->id]);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => 'A']);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => 'B']);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'path' => 'A']);

    $this->actingAs($user);
    Filament::setTenant($project);

    $component = Livewire::test(CreateFlowStep::class)
        ->fillForm(['feature_id' => $feature->id]);

    $options = $component->instance()->form->getComponent('path')->getDatalistOptions();

    expect($options)->toBe(['A', 'B']);
});

test('the file field offers the files already used by the selected feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $feature = Feature::factory()->create(['project_id' => $project->id]);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'file' => 'A.php']);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'file' => 'B.php']);
    FlowStep::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'file' => 'A.php']);

    $this->actingAs($user);
    Filament::setTenant($project);

    $component = Livewire::test(CreateFlowStep::class)
        ->fillForm(['feature_id' => $feature->id]);

    $options = $component->instance()->form->getComponent('file')->getDatalistOptions();

    expect($options)->toBe(['A.php', 'B.php']);
});
