<?php

namespace App\Console\Commands;

use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

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

    private const FEATURE_PATTERN = '/(?:feature\s*#?|功能\s*)(\d+)/iu';

    public function handle(): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        if (! $project->repo_path) {
            $this->error("Project \"{$project->slug}\" has no repo_path set.");

            return self::FAILURE;
        }

        $command = ['git', '-C', $project->repo_path, 'log', '--format=%H%x1f%an%x1f%aI%x1f%s%x1f%b%x1e'];

        if ($since = $this->option('since')) {
            $command[] = "--since={$since}";
        }

        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error("git log failed: {$process->getErrorOutput()}");

            return self::FAILURE;
        }

        $features = Feature::where('project_id', $project->id)->get()->keyBy('number');

        $created = 0;
        $updated = 0;
        $autoAssigned = 0;
        $unassigned = 0;

        foreach (explode("\x1e", trim($process->getOutput(), "\x1e\n")) as $entry) {
            if (trim($entry) === '') {
                continue;
            }

            [$hash, $author, $committedAt, $subject, $body] = array_pad(explode("\x1f", ltrim($entry, "\n"), 5), 5, '');
            $body = rtrim($body, "\n");

            $feature = $this->matchFeature($subject, $body, $features);

            $commit = Commit::withoutGlobalScopes()
                ->where('project_id', $project->id)
                ->where('hash', $hash)
                ->first();

            if ($commit) {
                $commit->fill([
                    'subject' => $subject,
                    'body' => $body === '' ? null : $body,
                    'author' => $author,
                    'committed_at' => Carbon::parse($committedAt),
                ]);

                if ($commit->feature_id === null && $feature) {
                    $commit->feature_id = $feature->id;
                    $autoAssigned++;
                }

                $commit->save();
                $updated++;
            } else {
                $commit = new Commit([
                    'hash' => $hash,
                    'subject' => $subject,
                    'body' => $body === '' ? null : $body,
                    'author' => $author,
                    'committed_at' => Carbon::parse($committedAt),
                    'feature_id' => $feature?->id,
                ]);
                // project_id is not mass-fillable (it is auto-filled from
                // the Filament tenant, which is not set in a console command).
                $commit->setAttribute('project_id', $project->id);
                $commit->save();
                $created++;

                if ($feature) {
                    $autoAssigned++;
                }
            }

            if (! $feature) {
                $unassigned++;
            }
        }

        $this->info("新增 {$created}、更新 {$updated}、自动挂上 {$autoAssigned}、未挂 {$unassigned}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Feature>  $features
     */
    private function matchFeature(string $subject, string $body, Collection $features): ?Feature
    {
        if (preg_match(self::FEATURE_PATTERN, $subject."\n".$body, $matches) !== 1) {
            return null;
        }

        return $features->get((int) $matches[1]);
    }
}
