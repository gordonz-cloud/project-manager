<?php

use App\Filament\Resources\Tests\Pages\ListTests;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the test list groups by its business area and shows which rules each test verifies [T125]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    $this->actingAs($user);
    Filament::setTenant($project);

    $refund = Requirement::factory()->create(['project_id' => $project->id, 'number' => 42]);
    $covered = Test::factory()->create(['project_id' => $project->id, 'module' => '退款', 'title' => '退款记账契约']);
    $covered->requirements()->attach($refund);
    $loose = Test::factory()->create(['project_id' => $project->id, 'module' => '物流', 'title' => '还没挂规则的测试']);

    Livewire::test(ListTests::class)
        ->assertCanSeeTableRecords([$covered, $loose])
        ->assertSee('退款')
        ->assertSee('物流')
        ->assertSee('R42');
});
