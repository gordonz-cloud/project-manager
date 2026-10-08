<?php

use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\Modules\ModuleDependencies;
use App\Services\Requirements\RequirementDependencies;
use App\Services\Requirements\RequirementVersionOptions;
use Illuminate\Validation\ValidationException;

test('module and requirement dependency validation preserve their form messages [T4] [T31]', function () {
    $project = Project::factory()->create();
    $moduleA = Module::factory()->create(['project_id' => $project->id]);
    $moduleB = Module::factory()->create(['project_id' => $project->id]);
    $moduleC = Module::factory()->create(['project_id' => $project->id]);
    $moduleA->dependsOn()->attach($moduleB);
    $moduleB->dependsOn()->attach($moduleC);

    expect(fn () => app(ModuleDependencies::class)->validate($moduleC, [$moduleA->id]))
        ->toThrow(
            ValidationException::class,
            "{$moduleA->name} 已经（直接或间接）依赖 {$moduleC->name}，不能反过来",
        );

    $requirementA = Requirement::factory()->create(['project_id' => $project->id]);
    $requirementB = Requirement::factory()->create(['project_id' => $project->id]);
    $requirementC = Requirement::factory()->create(['project_id' => $project->id]);
    $requirementA->dependsOn()->attach($requirementB);
    $requirementB->dependsOn()->attach($requirementC);

    expect(fn () => app(RequirementDependencies::class)->validate($requirementC, [$requirementA->id]))
        ->toThrow(
            ValidationException::class,
            "{$requirementA->title} 已经（直接或间接）依赖 {$requirementC->title}，不能反过来",
        );
});

test('version option queries stay in the service layer', function () {
    $project = Project::factory()->create();
    Requirement::factory()->create(['project_id' => $project->id, 'version' => 'v1']);
    Requirement::factory()->create([
        'project_id' => $project->id,
        'version' => null,
    ]);

    expect(app(RequirementVersionOptions::class)->all())
        ->toBe(['v1' => 'v1']);
});
