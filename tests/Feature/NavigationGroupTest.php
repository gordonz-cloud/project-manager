<?php

use App\Enums\NavigationGroup;
use App\Filament\Pages\RequirementTree;
use App\Filament\Pages\TestTree;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\ModelFields\ModelFieldResource;
use App\Filament\Resources\Requirements\RequirementResource;
use App\Filament\Resources\Tests\TestResource;

test('resources are grouped by solution model layer', function () {
    $groups = [
        RequirementTree::class => NavigationGroup::Scope,
        TestTree::class => NavigationGroup::Scope,
        RequirementResource::class => NavigationGroup::Requirements,
        DataModelResource::class => NavigationGroup::Solution,
        ModelFieldResource::class => NavigationGroup::Solution,
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
        'Solution',
        'Evidence',
    ]);
});
