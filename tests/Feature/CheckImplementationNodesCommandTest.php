<?php

use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Project;

function checkNodesRepoPath(): string
{
    return base_path('tests/Fixtures/repo');
}

test('passes when every file and function exists', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/OrderController.php',
        'function' => 'store',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});

test('flags a function that no longer exists in an existing file', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/OrderController.php',
        'function' => 'destroy',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('函数不存在')
        ->assertFailed();
});

test('flags a file that no longer exists', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/Gone.php',
        'function' => 'store',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('文件不存在')
        ->assertFailed();
});

test('strips a trailing line number before checking the file', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/OrderController.php:123',
        'function' => 'store',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});

test('skips function checking for a non-identifier description', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/OrderController.php',
        'function' => 'saved 钩子',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});

test('passes when either side of an either/or function exists', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'app/OrderController.php',
        'function' => 'claim / store',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});

test('checks a tsx file for a function-like definition', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'file' => 'resources/js/Checkout.tsx',
        'function' => 'submit',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});

test('scopes to one feature with --feature', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $feature1 = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);
    $feature2 = Feature::factory()->create(['project_id' => $project->id, 'number' => 2]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature1->id,
        'file' => 'app/OrderController.php',
        'function' => 'store',
    ]);

    ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature2->id,
        'file' => 'app/Gone.php',
        'function' => 'store',
    ]);

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg', '--feature' => '1'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();
});
