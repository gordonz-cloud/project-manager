<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Commits\SyncProjectCommits;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/**
 * Syncs a project's `commits` from its local repo's `git log`
 * (`Project.repo_path`), upserting by (project_id, hash). A commit whose
 * subject or body names a feature number ("Feature 12", "功能 12") gets
 * auto-assigned to it, unless the commit already carries a feature_id —
 * from an earlier auto-match or a manual pick — which is never overwritten.
 *
 * A bare "#N" is deliberately not matched: on a GitHub-hosted repo it is a
 * PR or issue number ("Merge pull request #20"), not a feature reference,
 * and matching it mis-assigns commits to unrelated features.
 */
class CommitsSyncCommand extends Command
{
    protected $signature = 'commits:sync {project-slug} {--since=}';

    protected $description = "Sync a project's commits from its repo's git log";

    public function handle(SyncProjectCommits $syncProjectCommits): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        try {
            $result = $syncProjectCommits->handle($project, $this->option('since'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        return self::SUCCESS;
    }
}
