<?php

use App\Filament\Resources\Tests\Pages\ListTests;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the test list groups by feature without asking Filament to sort a many-to-many [T125]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $refund = Feature::factory()->create(['project_id' => $project->id, 'title' => '运营在后台发起退款']);
    $covered = Test::factory()->create(['project_id' => $project->id, 'title' => '退款记账契约']);
    $covered->features()->attach($refund);
    $loose = Test::factory()->create(['project_id' => $project->id, 'title' => '还没挂功能的测试']);

    // Grouping straight on features.title throws: Filament sorts the query by
    // the grouped relation and refuses BelongsToMany. The subquery stands in.
    Livewire::test(ListTests::class)
        ->set('tableGrouping', 'feature')
        ->assertCanSeeTableRecords([$covered, $loose])
        ->assertSee('运营在后台发起退款')
        ->assertSee('（未挂功能）');
});

test('the test list can be narrowed to one module, reached through its features [T125]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $shipping = Module::factory()->create(['project_id' => $project->id, 'name' => '物流']);
    $tracked = Requirement::factory()->create(['project_id' => $project->id]);
    $tracked->modules()->attach($shipping);
    $track = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $tracked->id]);
    $inside = Test::factory()->create(['project_id' => $project->id]);
    $inside->features()->attach($track);
    $outside = Test::factory()->create(['project_id' => $project->id]);

    Livewire::test(ListTests::class)
        ->filterTable('module', $shipping->id)
        ->assertCanSeeTableRecords([$inside])
        ->assertCanNotSeeTableRecords([$outside]);
});
