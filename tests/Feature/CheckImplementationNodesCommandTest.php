<?php

use App\Enums\ImplementationNodeKind;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\UseCase;
use App\Models\UseCaseGroup;

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

test('scopes to one entry with --entry and names the entry', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => checkNodesRepoPath()]);
    $useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $project->id])]);
    $store = RequestReply::factory()->create(['use_case_id' => $useCase->id, 'method' => 'POST', 'entry' => '/orders']);
    $gone = RequestReply::factory()->create(['use_case_id' => $useCase->id, 'method' => null, 'entry' => 'orders:gone']);

    foreach ([[$store, 'app/OrderController.php'], [$gone, 'app/Gone.php']] as [$requestReply, $file]) {
        ImplementationNode::factory()->create([
            'project_id' => $project->id,
            'feature_id' => null,
            'request_reply_id' => $requestReply->id,
            'kind' => ImplementationNodeKind::Function,
            'file' => $file,
            'function' => 'store',
        ]);
    }

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg', '--entry' => 'POST /orders'])
        ->expectsOutputToContain('1 行核对通过')
        ->assertSuccessful();

    $this->artisan('implementation-nodes:check', ['project-slug' => 'sg', '--entry' => 'orders:gone'])
        ->expectsOutputToContain('入口 orders:gone')
        ->assertFailed();
});
