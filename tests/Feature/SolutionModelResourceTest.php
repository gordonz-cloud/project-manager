<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\Modules\Pages\CreateModule;
use App\Filament\Resources\ModuleSpecs\Pages\CreateModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\EditModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\ListModuleSpecs;
use App\Filament\Resources\ModuleSpecs\RelationManagers\UseCasesRelationManager;
use App\Filament\Resources\UseCaseGroups\Pages\CreateUseCaseGroup;
use App\Filament\Resources\UseCaseGroups\Pages\ListUseCaseGroups;
use App\Filament\Resources\UseCases\Pages\EditUseCase;
use App\Filament\Resources\UseCases\Pages\ListUseCases;
use App\Filament\Resources\UseCases\RelationManagers\FeaturesRelationManager as FeatureUseCaseRelationManager;
use App\Filament\Resources\WorkflowRuns\Pages\EditWorkflowRun;
use App\Filament\Resources\WorkflowRuns\Pages\ListWorkflowRuns;
use App\Filament\Resources\WorkflowRuns\RelationManagers\EventsRelationManager;
use App\Models\Feature;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\RunEvent;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\User;
use App\Models\WorkflowRun;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

function solutionModelContext(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $run = WorkflowRun::factory()->forUseCase($useCase)->create([
        'feature_id' => $feature->id,
    ]);
    $event = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
    ]);

    return compact(
        'project',
        'spec',
        'useCase',
        'feature',
        'run',
        'event',
    );
}

test('solution model resources list their tenant records [T2]', function () {
    $records = solutionModelContext();

    Livewire::test(ListModuleSpecs::class)->assertCanSeeTableRecords([$records['spec']]);
    Livewire::test(ListUseCases::class)->assertCanSeeTableRecords([$records['useCase']]);
    Livewire::test(ListWorkflowRuns::class)->assertCanSeeTableRecords([$records['run']]);
});

test('a module spec can be created for a module once [T3]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $module = Module::factory()->create([
        'project_id' => $project->id,
        'name' => 'Spec module',
    ]);

    auth()->login($user);
    Filament::setTenant($project);

    Livewire::test(CreateModuleSpec::class)
        ->fillForm([
            'module_id' => $module->id,
            'version' => 1,
            'status' => 'active',
            'summary' => 'Complete module spec',
            'content' => 'This spec covers the module.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ModuleSpec::query()->where('module_id', $module->id)->count())->toBe(1);
});

test('creating a module requires its spec content [T3]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    Livewire::test(CreateModule::class)
        ->fillForm(['name' => 'Orders'])
        ->call('create')
        ->assertHasFormErrors(['spec.content' => 'required']);

    Livewire::test(CreateModule::class)
        ->fillForm(['name' => 'Orders', 'spec.content' => 'Owns the order lifecycle.'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Module::query()->where('name', 'Orders')->sole()->spec->content)
        ->toBe('Owns the order lifecycle.');
});

test('solution model relation managers render the linked records [T34] [T94]', function () {
    $records = solutionModelContext();

    Livewire::test(UseCasesRelationManager::class, [
        'ownerRecord' => $records['spec'],
        'pageClass' => EditModuleSpec::class,
    ])->assertCanSeeTableRecords([$records['useCase']]);

    Livewire::test(EventsRelationManager::class, [
        'ownerRecord' => $records['run'],
        'pageClass' => EditWorkflowRun::class,
    ])->assertCanSeeTableRecords([$records['event']]);
});

test('owner-derived relation managers create records with the owner context [T94] [T99]', function () {
    $records = solutionModelContext();

    Livewire::test(UseCasesRelationManager::class, [
        'ownerRecord' => $records['spec'],
        'pageClass' => EditModuleSpec::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'use_case_group_id' => $records['useCase']->use_case_group_id,
            'actor' => '客户',
            'goal' => 'Owner context use case',
            'success_outcome' => 'Use case is created',
            'status' => 'draft',
            'spec' => ['content' => '客户下单后跨模块流转。'],
        ])
        ->assertHasNoFormErrors();

    $useCase = UseCase::query()->where('goal', 'Owner context use case')->sole();

    expect($useCase->modules()->pluck('modules.id')->all())->toBe([$records['spec']->module_id])
        ->and($useCase->spec?->content)->toBe('客户下单后跨模块流转。');

    Livewire::test(FeatureUseCaseRelationManager::class, [
        'ownerRecord' => $records['useCase'],
        'pageClass' => EditUseCase::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'title' => 'Owner context feature',
            'status' => FeatureStatus::Todo->value,
            'layers' => ['后端'],
            'triggers' => ['HTTP'],
            'entry' => 'owner-context',
        ])
        ->assertHasNoFormErrors();

    $feature = Feature::query()->where('title', 'Owner context feature')->sole();

    expect($feature->use_case_id)->toBe($records['useCase']->id);
});

test('use case groups are listed per project and can be created [T6]', function () {
    $records = solutionModelContext();
    $otherGroup = UseCaseGroup::factory()->create(['name' => 'Other tenant group']);

    Livewire::test(ListUseCaseGroups::class)
        ->assertCanSeeTableRecords([$records['useCase']->group])
        ->assertCanNotSeeTableRecords([$otherGroup]);

    Livewire::test(CreateUseCaseGroup::class)
        ->fillForm(['name' => 'Checkout', 'sort_order' => 3])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(UseCaseGroup::query()->where('name', 'Checkout')->value('project_id'))->toBe($records['project']->id);
});
