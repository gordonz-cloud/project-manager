<?php

use App\Filament\Resources\Requirements\Pages\EditRequirement;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a requirement cannot depend on something that already depends on it, transitively [T31]', function () {
    $project = Project::factory()->create();
    $a = Requirement::factory()->create(['project_id' => $project->id]);
    $b = Requirement::factory()->create(['project_id' => $project->id]);
    $c = Requirement::factory()->create(['project_id' => $project->id]);
    $a->dependsOn()->attach($b);
    $b->dependsOn()->attach($c);

    expect(Requirement::wouldCycle($c->id, $a->id))->toBeTrue();
    expect(Requirement::wouldCycle($a->id, $a->id))->toBeTrue();
    expect(Requirement::wouldCycle($a->id, $c->id))->toBeFalse();
});

test('build order puts a requirement after the ones it depends on, even across module order [T32]', function () {
    $project = Project::factory()->create();
    $orders = Module::factory()->create(['project_id' => $project->id, 'name' => '订单']);
    $panel = Module::factory()->create(['project_id' => $project->id, 'name' => '经营面板']);
    $panel->dependsOn()->attach($orders);

    $overview = Requirement::factory()->create(['project_id' => $project->id]);
    $overview->modules()->attach($panel);
    $desk = Requirement::factory()->create(['project_id' => $project->id]);
    $desk->modules()->attach($panel);
    $ordersPull = Requirement::factory()->create(['project_id' => $project->id]);
    $ordersPull->modules()->attach($orders);

    $desk->dependsOn()->attach($overview);
    // The orders requirement's module is built earlier, but it depends on the
    // panel's overview, so it must still sort after it.
    $ordersPull->dependsOn()->attach($overview);

    $order = Requirement::inBuildOrder($project)->pluck('id')->values()->all();

    expect(array_search($overview->id, $order))->toBeLessThan(array_search($desk->id, $order));
    expect(array_search($overview->id, $order))->toBeLessThan(array_search($ordersPull->id, $order));
});

test('without requirement dependencies the build order still follows module order then id [T32]', function () {
    $project = Project::factory()->create();
    $orders = Module::factory()->create(['project_id' => $project->id, 'name' => '订单']);
    $panel = Module::factory()->create(['project_id' => $project->id, 'name' => '经营面板']);
    $panel->dependsOn()->attach($orders);

    $panelRequirement = Requirement::factory()->create(['project_id' => $project->id]);
    $panelRequirement->modules()->attach($panel);
    $ordersRequirement = Requirement::factory()->create(['project_id' => $project->id]);
    $ordersRequirement->modules()->attach($orders);

    expect(Requirement::inBuildOrder($project)->pluck('id')->all())
        ->toBe([$ordersRequirement->id, $panelRequirement->id]);
});

test('the dependency select on a requirement\'s form excludes itself and other projects [T97]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);
    $other = Requirement::factory()->create(['project_id' => $project->id]);
    $foreign = Requirement::factory()->create(['project_id' => Project::factory()->create()->id]);

    $this->actingAs($user);
    Filament::setTenant($project);

    $test = Livewire::test(EditRequirement::class, ['record' => $requirement->getRouteKey()]);
    $options = $test->instance()->form->getComponent('dependsOn')->getSearchResults('');

    expect($options)->toHaveKey((string) $other->id);
    expect($options)->not->toHaveKey((string) $requirement->id);
    expect($options)->not->toHaveKey((string) $foreign->id);
});
