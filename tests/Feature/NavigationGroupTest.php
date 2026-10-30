<?php

use App\Enums\NavigationGroup;
use App\Filament\Pages\RequirementTree;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\ModelFields\ModelFieldResource;
use App\Filament\Resources\Requirements\RequirementResource;

test('resources are grouped by solution model layer', function () {
    $groups = [
        RequirementTree::class => NavigationGroup::Scope,
        RequirementResource::class => NavigationGroup::Requirements,
        DataModelResource::class => NavigationGroup::Solution,
        ModelFieldResource::class => NavigationGroup::Solution,
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
