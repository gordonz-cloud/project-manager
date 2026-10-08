<?php

use App\Enums\RunEventType;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Feature;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RunEvent;
use App\Models\UseCase;
use App\Models\WorkflowRun;

test('module spec and use case hierarchy connect the executable behavior chain [T7]', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create([
        'status' => UseCaseStatus::Ready,
    ]);
    expect($spec->useCases()->first()->is($useCase))->toBeTrue()
        ->and($useCase->status)->toBe(UseCaseStatus::Ready);
});

test('runtime keeps append-only events [T34]', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $run = WorkflowRun::factory()->forUseCase($useCase)->create([
        'feature_id' => $feature->id,
        'status' => WorkflowRunStatus::Running,
    ]);
    $message = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
        'event_type' => RunEventType::MessageAdded,
        'payload' => ['role' => 'user', 'content' => '继续'],
    ]);

    expect($run->events()->count())->toBe(1)
        ->and($message->payload)->toBe(['role' => 'user', 'content' => '继续'])
        ->and($message->created_at)->not->toBeNull();

    expect(fn () => $message->update(['payload' => ['role' => 'user']]))
        ->toThrow(LogicException::class);

    expect(fn () => $message->delete())
        ->toThrow(LogicException::class);

    expect(fn () => $run->delete())
        ->toThrow(LogicException::class);
});

test('use cases span several modules and features stay under their use case [T7] [T100]', function () {
    $project = Project::factory()->create();
    $billing = ModuleSpec::factory()->forProject($project)->create();
    $shipping = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($billing->module)->create();
    $useCase->modules()->attach($shipping->module);
    $feature = Feature::factory()->forUseCase($useCase)->create(['module_id' => $shipping->module_id]);

    expect($billing->useCases()->first()->is($useCase))->toBeTrue()
        ->and($shipping->module->useCases()->first()->is($useCase))->toBeTrue()
        ->and($useCase->modules()->count())->toBe(2)
        ->and($feature->module_id)->toBe($shipping->module_id)
        ->and($feature->project_id)->toBe($project->id);

    $outsider = ModuleSpec::factory()->forProject($project)->create();

    expect(fn () => Feature::factory()->forUseCase($useCase)->create(['module_id' => $outsider->module_id]))
        ->toThrow(LogicException::class);
});

test('a workflow run stays inside its use case [T34]', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $otherUseCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $otherFeature = Feature::factory()->forUseCase($otherUseCase)->create();

    $derived = WorkflowRun::factory()->create([
        'use_case_id' => null,
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);

    expect($derived->use_case_id)->toBe($useCase->id);

    expect(fn () => WorkflowRun::factory()->forUseCase($useCase)->create(['feature_id' => $otherFeature->id]))
        ->toThrow(LogicException::class, 'workflow use case');
});

test('saving a feature under a use case keeps its own requirement [T102]', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $requirement = Requirement::factory()->create(['project_id' => $project->id]);

    $feature = Feature::factory()->forUseCase($useCase)->create(['requirement_id' => $requirement->id]);
    $feature->update(['title' => 'Renamed']);

    expect($feature->fresh()->requirement_id)->toBe($requirement->id);
});
