<?php

use App\Filament\Resources\Requirements\Pages\ListRequirements;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('requirement list only shows requirements for the current tenant [T2]', function () {
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
