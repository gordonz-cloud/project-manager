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
 * message names rules ("R123", several allowed: "R123 R124") is linked to
 * every one of them, unless it is already linked to some rule — from an
 * earlier match or a manual pick — which is never overwritten.
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

        if ($project->repo_path) {
            $this->call('flowcharts:check', ['project-slug' => $project->slug]);
        }

        return self::SUCCESS;
    }
}
