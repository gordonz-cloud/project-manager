<?php

use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Module;
use App\Models\Project;
use App\Models\Scenario;
use App\Models\UseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('flow steps become a main call chain with failure branches hanging off the step before them', function () {
    $project = Project::factory()->create();
    $module = Module::factory()->create(['project_id' => $project->id]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'module_id' => $module->id]);

    Schema::create('flow_steps', function ($table) {
        $table->id();
        foreach (['project_id', 'feature_id', 'order'] as $column) {
            $table->integer($column);
        }
        foreach (['path', 'step', 'file', 'function', 'input', 'change', 'output'] as $column) {
            $table->string($column)->nullable();
        }
    });
    $step = fn (string $path, int $order, string $step) => DB::table('flow_steps')->insert([
        'project_id' => $project->id, 'feature_id' => $feature->id, 'path' => $path, 'order' => $order,
        'step' => $step, 'file' => "app/{$step}.php", 'function' => 'handle',
    ]);
    $step('主路径', 1, 'Route');
    $step('主路径', 2, 'Validate');
    $step('主路径', 3, 'Save');
    $step('金额错误分支', 1, 'Route');
    $step('金额错误分支', 3, 'Reject');

    (require base_path('database/migrations/2026_09_23_024051_move_flow_steps_into_implementation_nodes.php'))->up();

    $nodes = ImplementationNode::query()->where('feature_id', $feature->id)->pluck('id', 'title');
    $edges = DB::table('implementation_node_edges')->get()
        ->map(fn ($edge) => [$nodes->search($edge->from_node_id), $nodes->search($edge->to_node_id), $edge->kind])
        ->all();

    expect($nodes->keys()->all())->toBe(['Route', 'Validate', 'Save', 'Reject'])
        ->and($edges)->toBe([
            ['Route', 'Validate', 'calls'],
            ['Validate', 'Save', 'calls'],
            ['Validate', 'Reject', 'on_failure'],
        ])
        ->and(ImplementationNode::query()->whereNot('module_id', $module->id)->exists())->toBeFalse()
        ->and(Schema::hasTable('flow_steps'))->toBeFalse();
});

test('a scenario can only end on a node of its own use case', function () {
    $useCase = UseCase::factory()->create();
    $ownNode = ImplementationNode::factory()->create([
        'project_id' => $useCase->project_id,
        'feature_id' => Feature::factory()->forUseCase($useCase),
    ]);
    $foreignNode = ImplementationNode::factory()->create(['project_id' => $useCase->project_id]);

    $scenario = Scenario::factory()->create(['project_id' => $useCase->project_id, 'use_case_id' => $useCase->id, 'end_node_id' => $ownNode->id]);

    expect($scenario->endNode->is($ownNode))->toBeTrue()
        ->and(fn () => $scenario->update(['end_node_id' => $foreignNode->id]))
        ->toThrow(LogicException::class, 'same use case');
});

test('a use case takes part in its entries modules and its call-tree nodes modules', function () {
    $useCase = UseCase::factory()->create();
    [$entryModule, $nodeModule, $unrelated] = Module::factory()->count(3)->sequence(['name' => 'A entry'], ['name' => 'B node'], ['name' => 'C other'])
        ->create(['project_id' => $useCase->project_id]);
    $useCase->modules()->attach($entryModule);
    $feature = Feature::factory()->forUseCase($useCase)->create(['module_id' => $entryModule->id]);
    ImplementationNode::factory()->create(['project_id' => $useCase->project_id, 'feature_id' => $feature->id, 'module_id' => $nodeModule->id]);
    ImplementationNode::factory()->create(['project_id' => $useCase->project_id, 'module_id' => $unrelated->id]);

    expect($useCase->participatingModules()->pluck('name')->all())->toBe(['A entry', 'B node']);
});
