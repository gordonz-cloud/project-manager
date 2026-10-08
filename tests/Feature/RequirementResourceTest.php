<?php

use App\Filament\Resources\Requirements\Pages\CreateRequirement;
use App\Filament\Resources\Requirements\Pages\ListRequirements;
use App\Models\Module;
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

test('requirement form module select only offers modules from the current tenant [T97]', function () {
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

test('the requirement list groups by module without asking Filament to sort a many-to-many [T97]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $shipping = Module::factory()->create(['project_id' => $project->id, 'name' => '物流']);
    $tracked = Requirement::factory()->create(['project_id' => $project->id, 'title' => '包裹可追踪']);
    $tracked->modules()->attach($shipping);
    $loose = Requirement::factory()->create(['project_id' => $project->id, 'title' => '还没归模块']);

    // Grouping straight on modules.name threw: Filament sorts the query by the
    // grouped relation and refuses BelongsToMany. The subquery order stands in.
    Livewire::test(ListRequirements::class)
        ->set('tableGrouping', 'module')
        ->assertCanSeeTableRecords([$tracked, $loose])
        ->assertSee('物流')
        ->assertSee('（无模块）');
});
