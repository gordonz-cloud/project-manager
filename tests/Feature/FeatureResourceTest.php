<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\Features\Pages\CreateFeature;
use App\Filament\Resources\Features\Pages\ListFeatures;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('creating a feature assigns the current tenant and the first sequence number', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p1->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(CreateFeature::class)
        ->fillForm([
            'title' => 'Checkout flow',
            'status' => FeatureStatus::Todo->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $feature = Feature::first();

    expect($feature->project_id)->toBe($p1->id);
    expect($feature->number)->toBe(1);
});

test('the feature list groups by module, reached through each feature\'s requirement', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $shipping = Module::factory()->create(['project_id' => $project->id, 'name' => '物流']);
    $billing = Module::factory()->create(['project_id' => $project->id, 'name' => '结账']);
    $trackParcels = Requirement::factory()->create(['project_id' => $project->id]);
    $payOnce = Requirement::factory()->create(['project_id' => $project->id]);
    $trackParcels->modules()->attach($shipping);
    $payOnce->modules()->attach($billing);
    $track = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $trackParcels->id, 'title' => '每日同步轨迹']);
    $pay = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $payOnce->id, 'title' => '唯一付款会话']);
    $orphan = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => null, 'title' => '没挂需求的功能']);

    // Grouping sorts by a subquery over module_requirement; a feature with no
    // requirement has no module and must still be listed, under its own head.
    Livewire::test(ListFeatures::class)
        ->set('tableGrouping', 'module')
        ->assertCanSeeTableRecords([$track, $pay, $orphan])
        ->assertSee('物流')
        ->assertSee('结账')
        ->assertSee('（无模块）');
});
