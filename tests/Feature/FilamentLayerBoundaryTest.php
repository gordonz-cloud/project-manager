<?php

use App\Models\Project;
use App\Models\Requirement;
use App\Services\Requirements\RequirementDependencies;
use App\Services\Requirements\RequirementVersionOptions;
use Illuminate\Validation\ValidationException;

test('requirement dependency validation preserves its form message [T31]', function () {
    $project = Project::factory()->create();
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
