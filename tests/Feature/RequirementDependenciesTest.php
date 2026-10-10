<?php

use App\Filament\Resources\Requirements\Pages\EditRequirement;
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

test('build order puts a requirement after the ones it depends on, then lower id first [T32]', function () {
    $project = Project::factory()->create();
    $desk = Requirement::factory()->create(['project_id' => $project->id]);
    $overview = Requirement::factory()->create(['project_id' => $project->id]);
    $loose = Requirement::factory()->create(['project_id' => $project->id]);
    $desk->dependsOn()->attach($overview);

    expect(Requirement::inBuildOrder($project)->pluck('id')->all())->toBe([$overview->id, $desk->id, $loose->id]);
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
