<?php

use App\Enums\NavigationGroup;
use App\Filament\Pages\WorkbenchGraph;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\FlowSteps\FlowStepResource;
use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use App\Filament\Resources\ModelFields\ModelFieldResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use App\Filament\Resources\Requirements\RequirementResource;
use App\Filament\Resources\Scenarios\ScenarioResource;
use App\Filament\Resources\Tests\TestResource;
use App\Filament\Resources\UseCaseGroups\UseCaseGroupResource;
use App\Filament\Resources\UseCases\UseCaseResource;
use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;

test('resources are grouped by solution model layer', function () {
    $groups = [
        ModuleResource::class => NavigationGroup::Scope,
        ModuleSpecResource::class => NavigationGroup::Scope,
        WorkbenchGraph::class => NavigationGroup::Scope,
        RequirementResource::class => NavigationGroup::Requirements,
        UseCaseGroupResource::class => NavigationGroup::Behavior,
        UseCaseResource::class => NavigationGroup::Behavior,
        ScenarioResource::class => NavigationGroup::Behavior,
        DataModelResource::class => NavigationGroup::Solution,
        ModelFieldResource::class => NavigationGroup::Solution,
        FeatureResource::class => NavigationGroup::Delivery,
        ImplementationNodeResource::class => NavigationGroup::Delivery,
        WorkflowRunResource::class => NavigationGroup::Execution,
        FlowStepResource::class => NavigationGroup::Evidence,
        TestResource::class => NavigationGroup::Evidence,
        CommitResource::class => NavigationGroup::Evidence,
    ];

    foreach ($groups as $resource => $group) {
        expect($resource::getNavigationGroup())->toBe($group);
    }

    expect(array_map(
        fn (NavigationGroup $group): string => $group->getLabel(),
        NavigationGroup::cases(),
    ))->toBe([
        'Scope',
        'Requirements',
        'Behavior',
        'Solution',
        'Delivery',
        'Execution',
        'Evidence',
    ]);
});
