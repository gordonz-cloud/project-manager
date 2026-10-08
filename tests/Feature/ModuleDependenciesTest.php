<?php

use App\Filament\Resources\Modules\Pages\EditModule;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a module cannot depend on something that already depends on it, directly [T4]', function () {
    $project = Project::factory()->create();
    $a = Module::factory()->create(['project_id' => $project->id]);
    $b = Module::factory()->create(['project_id' => $project->id]);
    $a->dependsOn()->attach($b);

    expect(Module::wouldCycle($b->id, $a->id))->toBeTrue();
});

test('a module cannot depend on something that already depends on it, transitively [T4]', function () {
    $project = Project::factory()->create();
    $a = Module::factory()->create(['project_id' => $project->id]);
    $b = Module::factory()->create(['project_id' => $project->id]);
    $c = Module::factory()->create(['project_id' => $project->id]);
    $a->dependsOn()->attach($b);
    $b->dependsOn()->attach($c);

    expect(Module::wouldCycle($c->id, $a->id))->toBeTrue();
});

test('a module can depend on something unrelated [T93]', function () {
    $project = Project::factory()->create();
    $a = Module::factory()->create(['project_id' => $project->id]);
    $b = Module::factory()->create(['project_id' => $project->id]);

    expect(Module::wouldCycle($a->id, $b->id))->toBeFalse();
});

test('build order respects dependencies [T5]', function () {
    $project = Project::factory()->create();
    $member = Module::factory()->create(['project_id' => $project->id, 'name' => '会员']);
    $membershipRole = Module::factory()->create(['project_id' => $project->id, 'name' => '会籍角色']);
    $checkout = Module::factory()->create(['project_id' => $project->id, 'name' => '结账']);
    $rewards = Module::factory()->create(['project_id' => $project->id, 'name' => '奖励']);
    $membershipRole->dependsOn()->attach($member);
    $rewards->dependsOn()->attach($checkout);

    $order = Module::inBuildOrder($project)->pluck('name')->values()->all();

    expect(array_search('会员', $order))->toBeLessThan(array_search('会籍角色', $order));
    expect(array_search('结账', $order))->toBeLessThan(array_search('奖励', $order));
});

test('requirement build order follows its furthest-built module [T32]', function () {
    $project = Project::factory()->create();
    $member = Module::factory()->create(['project_id' => $project->id, 'name' => '会员']);
    $membershipRole = Module::factory()->create(['project_id' => $project->id, 'name' => '会籍角色']);
    $membershipRole->dependsOn()->attach($member);

    $memberRequirement = Requirement::factory()->create(['project_id' => $project->id]);
    $memberRequirement->modules()->attach($member);
    $roleRequirement = Requirement::factory()->create(['project_id' => $project->id]);
    $roleRequirement->modules()->attach($membershipRole);

    $order = Requirement::inBuildOrder($project)->pluck('id')->values()->all();

    expect(array_search($memberRequirement->id, $order))->toBeLessThan(array_search($roleRequirement->id, $order));
});

test('the dependency select on a module\'s form excludes itself [T93]', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $module = Module::factory()->create(['project_id' => $project->id]);
    $other = Module::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);
    Filament::setTenant($project);

    $test = Livewire::test(EditModule::class, ['record' => $module->getRouteKey()]);
    $options = $test->instance()->form->getComponent('dependsOn')->getSearchResults('');

    expect($options)->toHaveKey((string) $other->id);
    expect($options)->not->toHaveKey((string) $module->id);
});
