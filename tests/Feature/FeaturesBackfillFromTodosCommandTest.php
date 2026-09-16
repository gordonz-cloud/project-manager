<?php

use App\Enums\FeatureLayer;
use App\Models\Feature;
use App\Models\Project;

test('backfills layer and version from matching todos, lists what it cannot match, and is idempotent', function () {
    $project = Project::factory()->create(['slug' => 'sg']);

    $exact = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'Exact match feature',
        'layer' => null,
        'version' => null,
    ]);

    $punctuated = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'Punctuated match feature',
        'layer' => null,
        'version' => null,
    ]);

    $noMvp = Feature::factory()->create([
        'project_id' => $project->id,
        'title' => 'No MVP feature',
        'layer' => null,
        'version' => null,
    ]);

    $this->artisan('features:backfill-from-todos', [
        'project-slug' => 'sg',
        '--file' => base_path('tests/Fixtures/notion-export/todos.json'),
    ])
        ->expectsOutputToContain('匹配 3、未匹配 1')
        ->expectsOutputToContain('Nothing matches this title')
        ->assertSuccessful();

    expect($exact->fresh()->layer)->toBe(FeatureLayer::Backend)
        ->and($exact->fresh()->version)->toBe('1')
        ->and($punctuated->fresh()->layer)->toBe(FeatureLayer::Frontend)
        ->and($punctuated->fresh()->version)->toBe('1')
        ->and($noMvp->fresh()->layer)->toBe(FeatureLayer::Admin)
        ->and($noMvp->fresh()->version)->toBeNull();

    // Running it again does not clobber existing values.
    $exact->update(['layer' => FeatureLayer::Manual, 'version' => '2']);

    $this->artisan('features:backfill-from-todos', [
        'project-slug' => 'sg',
        '--file' => base_path('tests/Fixtures/notion-export/todos.json'),
    ])->assertSuccessful();

    expect($exact->fresh()->layer)->toBe(FeatureLayer::Manual)
        ->and($exact->fresh()->version)->toBe('2');
});
