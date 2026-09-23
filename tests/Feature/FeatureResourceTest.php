<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\Features\Pages\CreateFeature;
use App\Filament\Resources\Features\Pages\ListFeatures;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Project;
use App\Models\UseCase;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('creating a feature assigns the current tenant and the first sequence number', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p1->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($p1);
    $module = Module::factory()->create(['project_id' => $p1->id]);
    $useCase = UseCase::factory()->forModule($module)->create();

    Livewire::test(CreateFeature::class)
        ->fillForm([
            'title' => 'Checkout flow',
            'status' => FeatureStatus::Todo->value,
            'use_case_id' => $useCase->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $feature = Feature::first();

    expect($feature->project_id)->toBe($p1->id);
    expect($feature->number)->toBe(1);
    expect($feature->use_case_id)->toBe($useCase->id);
});

test('the feature list groups by each feature\'s own module', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $shipping = Module::factory()->create(['project_id' => $project->id, 'name' => '物流']);
    $billing = Module::factory()->create(['project_id' => $project->id, 'name' => '结账']);
    $trackUseCase = UseCase::factory()->forModule($shipping)->create();
    $payUseCase = UseCase::factory()->forModule($billing)->create();
    $track = Feature::factory()->forUseCase($trackUseCase)->create(['title' => '每日同步轨迹']);
    $pay = Feature::factory()->forUseCase($payUseCase)->create(['title' => '唯一付款会话']);
    $orphan = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => null, 'title' => '没挂需求的功能']);

    // A feature with no module must still be listed, under its own head.
    Livewire::test(ListFeatures::class)
        ->set('tableGrouping', 'module.name')
        ->assertCanSeeTableRecords([$track, $pay, $orphan])
        ->assertSee('物流')
        ->assertSee('结账');
});

test('the feature list still renders when a layer is not a FeatureLayer value', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $feature = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => '会员能把商品保存到 Wishlist',
        'layers' => ['后端', '数据库'],
    ]);

    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(ListFeatures::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$feature])
        ->assertSee('后端');
});
