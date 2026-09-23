<?php

use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Module;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\ImplementationNodes\ImplementationNodeSelection;
use App\Services\Modules\ModuleDependencies;
use App\Services\Requirements\RequirementDependencies;
use App\Services\Requirements\RequirementVersionOptions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

test('implementation node selection enforces the feature and acyclic parent rules', function () {
    $project = Project::factory()->create();
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    $otherFeature = Feature::factory()->create(['project_id' => $project->id]);
    $root = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $child = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'parent_id' => $root->id,
    ]);
    $otherFeatureNode = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $otherFeature->id,
    ]);
    $selection = app(ImplementationNodeSelection::class);
    $failureMessage = null;

    $selection->parentRule(null, $feature->id)(
        'parent_id',
        $otherFeatureNode->id,
        function (string $message) use (&$failureMessage): void {
            $failureMessage = $message;
        },
    );

    expect($failureMessage)->toBe('父节点必须属于同一个功能。');

    $failureMessage = null;
    $selection->parentRule($root, $feature->id)(
        'parent_id',
        $child->id,
        function (string $message) use (&$failureMessage): void {
            $failureMessage = $message;
        },
    );

    expect($failureMessage)->toBe('父节点不能是当前节点或它的后代。');
});

test('flow step implementation node validation accepts only nodes from the selected feature', function () {
    $project = Project::factory()->create();
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    $otherFeature = Feature::factory()->create(['project_id' => $project->id]);
    $node = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $otherNode = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $otherFeature->id,
    ]);
    $rule = app(ImplementationNodeSelection::class)->existsInFeatureRule($feature->id);

    expect(Validator::make(['implementation_node_id' => $node->id], ['implementation_node_id' => $rule])->passes())
        ->toBeTrue()
        ->and(Validator::make(['implementation_node_id' => $otherNode->id], ['implementation_node_id' => $rule])->fails())
        ->toBeTrue();
});

test('module and requirement dependency validation preserve their form messages', function () {
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
