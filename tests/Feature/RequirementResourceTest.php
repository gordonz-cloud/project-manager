<?php

use App\Enums\RequirementStatus;
use App\Filament\Resources\Requirements\Pages\CreateRequirement;
use App\Filament\Resources\Requirements\Pages\ListRequirements;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('requirement list only shows requirements for the current tenant', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $p1->users()->attach($user);

    $p1Requirement = Requirement::factory()->for($p1)->create();
    Requirement::factory()->for($p2)->create();

    $this->actingAs($user);
    Filament::setTenant($p1);

    Livewire::test(ListRequirements::class)
        ->assertCanSeeTableRecords([$p1Requirement])
        ->assertCountTableRecords(1);
});

test('requirement form module select only offers modules from the current tenant', function () {
    $user = User::factory()->create();
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $p1->users()->attach($user);

    $p1Module = Module::factory()->for($p1)->create();
    $p2Module = Module::factory()->for($p2)->create();

    $this->actingAs($user);
    Filament::setTenant($p1);

    $field = Livewire::test(CreateRequirement::class)
        ->instance()
        ->form
        ->getFlatFields()['modules'];

    $options = $field->getOptionsFromRelationship();

    expect($options)->toHaveKey($p1Module->id);
    expect($options)->not->toHaveKey($p2Module->id);
});

test('requirement status can be updated inline from the table', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $requirement = Requirement::factory()->for($project)->create(['status' => RequirementStatus::Pending]);

    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(ListRequirements::class)
        ->call('updateTableColumnState', 'status', $requirement->getKey(), RequirementStatus::Done->value);

    expect($requirement->refresh()->status)->toBe(RequirementStatus::Done);
});
