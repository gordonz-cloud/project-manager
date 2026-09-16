<?php

use App\Enums\FeatureLayer;
use App\Models\Feature;
use App\Models\Project;

test('backfills layer and version from matching todos, lists what it cannot match, and is idempotent', function () {
    $project = Project::factory()->create(['slug' => 'sg']);

    $exact = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'Exact match feature',
        'layers' => null,
        'version' => null,
    ]);

    $punctuated = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'Punctuated match feature',
        'layers' => null,
        'version' => null,
    ]);

    $noMvp = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'No MVP feature',
        'layers' => null,
        'version' => null,
    ]);

    $this->artisan('features:backfill-from-todos', [
        'project-slug' => 'sg',
        '--file' => base_path('tests/Fixtures/notion-export/todos.json'),
    ])
        ->expectsOutputToContain('high 应用 3、low 0、未匹配 3')
        ->expectsOutputToContain('[未匹配] Nothing matches this title')
        ->assertSuccessful();

    expect($exact->fresh()->layers)->toBe([FeatureLayer::Backend->value])
        ->and($exact->fresh()->version)->toBe('1')
        ->and($punctuated->fresh()->layers)->toBe([FeatureLayer::Frontend->value])
        ->and($punctuated->fresh()->version)->toBe('1')
        ->and($noMvp->fresh()->layers)->toBe([FeatureLayer::Admin->value])
        ->and($noMvp->fresh()->version)->toBeNull();

    // Running it again does not clobber existing values.
    $exact->update(['layers' => [FeatureLayer::Manual->value], 'version' => '2']);

    $this->artisan('features:backfill-from-todos', [
        'project-slug' => 'sg',
        '--file' => base_path('tests/Fixtures/notion-export/todos.json'),
    ])->assertSuccessful();

    expect($exact->fresh()->layers)->toBe([FeatureLayer::Manual->value])
        ->and($exact->fresh()->version)->toBe('2');
});

test('a --map file applies only its high confidence entries, matched by feature number', function () {
    $project = Project::factory()->create(['slug' => 'sg']);

    // First feature created gets number 1, per HasProjectSequence.
    $mappedFeature = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completely differently worded feature title',
        'layers' => null,
        'version' => null,
    ]);

    $this->artisan('features:backfill-from-todos', [
        'project-slug' => 'sg',
        '--file' => base_path('tests/Fixtures/notion-export/todos.json'),
        '--map' => base_path('tests/Fixtures/notion-export/todo-feature-map.json'),
    ])
        ->expectsOutputToContain('high 应用 1、low 1、未匹配 4')
        ->expectsOutputToContain('[low] Ambiguous todo text → #1 — test fixture: not confident enough to apply')
        ->assertSuccessful();

    expect($mappedFeature->fresh()->layers)->toBe([FeatureLayer::Backend->value])
        ->and($mappedFeature->fresh()->version)->toBe('1');
});
