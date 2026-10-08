<?php

use App\Filament\Resources\Modules\Pages\EditModule;
use App\Filament\Resources\Modules\RelationManagers\RequirementsRelationManager;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the requirements relation manager lists only the module\'s own requirements [T98]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $module = Module::factory()->create(['project_id' => $project->id]);
    $attached = Requirement::factory()->count(2)->create(['project_id' => $project->id]);
    $other = Requirement::factory()->create(['project_id' => $project->id]);
    $module->requirements()->attach($attached);

    $this->actingAs($user);
    Filament::setTenant($project);

    Livewire::test(RequirementsRelationManager::class, [
        'ownerRecord' => $module,
        'pageClass' => EditModule::class,
    ])
        ->assertCanSeeTableRecords($attached)
        ->assertCanNotSeeTableRecords([$other]);
});
