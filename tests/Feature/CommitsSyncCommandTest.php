<?php

use App\Enums\FeatureStatus;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Symfony\Component\Process\Process;

function makeCommitTestRepo(): string
{
    $repo = storage_path('framework/testing/commits-sync-repo');

    (new Process(['rm', '-rf', $repo]))->run();
    (new Process(['mkdir', '-p', $repo]))->run();

    $git = fn (array $args) => (new Process(['git', '-C', $repo, ...$args]))->mustRun();

    $git(['init', '-q']);
    $git(['config', 'user.email', 'a@b.com']);
    $git(['config', 'user.name', 'Tester']);

    file_put_contents($repo.'/f', 'one');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Fix login bug', '-m', 'Mentions Feature 12 in the subject-adjacent title']);

    file_put_contents($repo.'/f', 'two');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Second commit', '-m', 'Body references 功能7 somewhere']);

    file_put_contents($repo.'/f', 'three');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Third commit, no feature reference']);

    file_put_contents($repo.'/f', 'four');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Fix #22', '-m', 'Merge pull request #22 from somewhere']);

    return $repo;
}

test('syncs commits from git log, auto-assigns by feature reference, and stays idempotent', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => makeCommitTestRepo()]);

    $feature12 = Feature::factory()->create(['project_id' => $project->id, 'number' => 12, 'status' => FeatureStatus::Todo]);
    $feature7 = Feature::factory()->create(['project_id' => $project->id, 'number' => 7, 'status' => FeatureStatus::Todo]);
    Feature::factory()->create(['project_id' => $project->id, 'number' => 22, 'status' => FeatureStatus::Todo]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])
        ->expectsOutputToContain('新增 4、更新 0、自动挂上 2、未挂 2')
        ->assertSuccessful();

    expect(Commit::count())->toBe(4);

    $bySubject = Commit::all()->keyBy('subject');

    expect($bySubject->get('Fix login bug')->feature_id)->toBe($feature12->id)
        ->and($bySubject->get('Second commit')->feature_id)->toBe($feature7->id)
        ->and($bySubject->get('Third commit, no feature reference')->feature_id)->toBeNull()
        // A bare "#N" is a PR/issue number on GitHub, not a feature
        // reference — "Fix #22" / "Merge pull request #22" must not
        // auto-assign to feature #22.
        ->and($bySubject->get('Fix #22')->feature_id)->toBeNull();

    // Idempotent: re-running does not create duplicates.
    $this->artisan('commits:sync', ['project-slug' => 'sg'])
        ->expectsOutputToContain('新增 0、更新 4、自动挂上 0、未挂 2')
        ->assertSuccessful();

    expect(Commit::count())->toBe(4);

    // A manual reassignment survives a re-sync.
    $unassigned = $bySubject->get('Third commit, no feature reference');
    $unassigned->update(['feature_id' => $feature7->id]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])->assertSuccessful();

    expect($unassigned->fresh()->feature_id)->toBe($feature7->id);
});

test('scopes commits to their project', function () {
    $project1 = Project::factory()->create(['slug' => 'p1', 'repo_path' => makeCommitTestRepo()]);
    Project::factory()->create(['slug' => 'p2']);

    $this->artisan('commits:sync', ['project-slug' => 'p1'])->assertSuccessful();

    $commit = Commit::first();

    expect($commit->project_id)->toBe($project1->id)
        ->and(Commit::withoutGlobalScopes()->where('project_id', '!=', $project1->id)->count())->toBe(0);
});

test('fails clearly when the project has no repo_path', function () {
    Project::factory()->create(['slug' => 'no-repo', 'repo_path' => null]);

    $this->artisan('commits:sync', ['project-slug' => 'no-repo'])->assertFailed();
});
