<?php

use App\Filament\Resources\Tests\Pages\ListTests;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the test list groups by feature without asking Filament to sort a many-to-many', function () {
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
