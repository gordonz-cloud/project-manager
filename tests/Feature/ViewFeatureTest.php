<?php

use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\Features\Pages\ListFeatures;
use App\Filament\Resources\Features\Pages\ViewFeature;
use App\Filament\Resources\Features\RelationManagers\FlowStepsRelationManager;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Module;
use App\Models\Project;
use App\Models\UseCase;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the feature list rows link to the view page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $feature = Feature::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(ListFeatures::class)
        ->assertSeeHtml('href="'.FeatureResource::getUrl('view', ['record' => $feature]).'"');
});

test('the view page renders the feature id, title, use case goal, and flow step input', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $module = Module::factory()->create(['project_id' => $project->id]);
    $useCase = UseCase::factory()->forModule($module)->create(['goal' => 'Checkout Works']);
    $feature = Feature::factory()->forUseCase($useCase)->create([
        'title' => 'Charge The Card',
        'number' => 42,
    ]);
    FlowStep::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'order' => 1,
        'input' => "amount: int\n  currency: string",
    ]);
    FlowStep::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'order' => 2,
        'input' => '<script>alert(1)</script>',
    ]);

    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(ViewFeature::class, ['record' => $feature->getKey()])
        ->assertSee('42')
        ->assertSee('Charge The Card')
        ->assertSee('Checkout Works');

    Livewire::test(FlowStepsRelationManager::class, ['ownerRecord' => $feature, 'pageClass' => ViewFeature::class])
        ->assertSee('amount: int')
        ->assertSeeHtml('amount: int<br')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee('&lt;script&gt;', escape: false);
});

test('a feature outside the current tenant 404s on the view page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $project->users()->attach($user);
    $otherProject->users()->attach($user);

    $feature = Feature::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user);
    Filament::setTenant($project);

    $this->get(FeatureResource::getUrl('view', ['record' => $feature, 'tenant' => $project->slug]))
        ->assertNotFound();
});
