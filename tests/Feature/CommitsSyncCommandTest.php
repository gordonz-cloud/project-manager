<?php

use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use Symfony\Component\Process\Process;

function makeCommitTestRepo(): string
{
    $repo = storage_path('framework/testing/commits-sync-repo-'.getmypid());

    (new Process(['rm', '-rf', $repo]))->run();
    (new Process(['mkdir', '-p', $repo]))->run();

    $git = fn (array $args) => (new Process(['git', '-C', $repo, ...$args]))->mustRun();

    $git(['init', '-q']);
    $git(['config', 'user.email', 'a@b.com']);
    $git(['config', 'user.name', 'Tester']);

    file_put_contents($repo.'/f', 'one');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Fix login bug', '-m', 'Works on R12 and R13']);

    file_put_contents($repo.'/f', 'two');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Second commit', '-m', 'Body references R7 somewhere, and Feature 12 the old way']);

    file_put_contents($repo.'/f', 'three');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Third commit, no rule reference']);

    file_put_contents($repo.'/f', 'four');
    $git(['add', 'f']);
    $git(['commit', '-q', '-m', 'Fix #22', '-m', 'Merge pull request #22 from somewhere']);

    return $repo;
}

test('syncs commits from git log, links each to every rule its message names, and stays idempotent [T21]', function () {
    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => makeCommitTestRepo()]);

    $rule12 = Requirement::factory()->create(['project_id' => $project->id, 'number' => 12]);
    $rule13 = Requirement::factory()->create(['project_id' => $project->id, 'number' => 13]);
    $rule7 = Requirement::factory()->create(['project_id' => $project->id, 'number' => 7]);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 22]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])
        ->expectsOutputToContain('新增 4、更新 0、自动挂上 2、未挂 2')
        ->assertSuccessful();

    expect(Commit::count())->toBe(4);

    $rulesOf = fn (string $subject): array => Commit::where('subject', $subject)->sole()->requirements()->orderBy('number')->pluck('number')->all();

    expect($rulesOf('Fix login bug'))->toBe([12, 13])
        ->and($rulesOf('Second commit'))->toBe([7])
        ->and($rulesOf('Third commit, no rule reference'))->toBe([])
        // A bare "#N" is a PR/issue number on GitHub, not a rule reference.
        ->and($rulesOf('Fix #22'))->toBe([]);

    // Idempotent: re-running does not create duplicates, and since nothing
    // actually changed, nothing is written back either.
    $this->artisan('commits:sync', ['project-slug' => 'sg'])
        ->expectsOutputToContain('新增 0、更新 0、自动挂上 0、未挂 2')
        ->assertSuccessful();

    expect(Commit::count())->toBe(4);

    // A manual relink survives a re-sync: the message's R7 is not added back.
    Commit::where('subject', 'Second commit')->sole()->requirements()->sync([$rule12->id]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])->assertSuccessful();

    expect($rulesOf('Second commit'))->toBe([12])
        ->and($rule13->commits()->count())->toBe(1);
});

test('only writes rows whose fields actually changed [T21]', function () {
    $repo = makeCommitTestRepo();
    Project::factory()->create(['slug' => 'sg', 'repo_path' => $repo]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])->assertSuccessful();

    $untouched = Commit::where('subject', 'Second commit')->first();
    $untouchedUpdatedAt = $untouched->updated_at;

    // Simulate the stored row drifting from what `git log` actually says
    // (e.g. edited by hand) for exactly one commit; the rest stay in sync.
    $stale = Commit::where('subject', 'Fix login bug')->first();
    $stale->forceFill(['author' => 'Someone Else'])->save();

    $this->travel(1)->hours();

    $this->artisan('commits:sync', ['project-slug' => 'sg'])
        ->expectsOutputToContain('新增 0、更新 1、自动挂上 0、未挂 4')
        ->assertSuccessful();

    expect($stale->fresh()->author)->toBe('Tester')
        ->and($untouched->fresh()->updated_at)->toEqual($untouchedUpdatedAt);
});

test('scopes commits to their project [T21]', function () {
    $project1 = Project::factory()->create(['slug' => 'p1', 'repo_path' => makeCommitTestRepo()]);
    Project::factory()->create(['slug' => 'p2']);

    $this->artisan('commits:sync', ['project-slug' => 'p1'])->assertSuccessful();

    $commit = Commit::first();

    expect($commit->project_id)->toBe($project1->id)
        ->and(Commit::withoutGlobalScopes()->where('project_id', '!=', $project1->id)->count())->toBe(0);
});

test('fails clearly when the project has no repo_path [T22]', function () {
    Project::factory()->create(['slug' => 'no-repo', 'repo_path' => null]);

    $this->artisan('commits:sync', ['project-slug' => 'no-repo'])->assertFailed();
});

test('syncs commits that only live on a remote-tracking branch [T21]', function () {
    $repo = makeCommitTestRepo();
    $git = fn (array $args) => (new Process(['git', '-C', $repo, ...$args]))->mustRun();

    $git(['checkout', '-q', '-b', 'side']);
    file_put_contents($repo.'/f', 'side');
    $git(['commit', '-q', '-am', 'Pushed elsewhere']);
    $git(['update-ref', 'refs/remotes/origin/development', 'HEAD']);
    $git(['checkout', '-q', '-']);
    $git(['branch', '-q', '-D', 'side']);

    $project = Project::factory()->create(['slug' => 'sg', 'repo_path' => $repo]);

    $this->artisan('commits:sync', ['project-slug' => 'sg'])->assertSuccessful();

    expect(Commit::where('project_id', $project->id)->pluck('subject'))->toContain('Pushed elsewhere');
});
