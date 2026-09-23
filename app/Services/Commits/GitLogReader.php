<?php

namespace App\Services\Commits;

use App\Data\Commits\GitCommitData;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class GitLogReader
{
    /**
     * @return Collection<int, GitCommitData>
     */
    public function read(Project $project, ?string $since = null): Collection
    {
        if (! $project->repo_path) {
            throw new InvalidArgumentException("Project \"{$project->slug}\" has no repo_path set.");
        }

        $command = [
            'git',
            '-C',
            $project->repo_path,
            'log',
            '--format=%H%x1f%an%x1f%aI%x1f%s%x1f%b%x1e',
        ];

        if ($since) {
            $command[] = "--since={$since}";
        }

        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("git log failed: {$process->getErrorOutput()}");
        }

        return $this->parse($process->getOutput());
    }

    /**
     * @return Collection<int, GitCommitData>
     */
    private function parse(string $output): Collection
    {
        $commits = collect();

        foreach (explode("\x1e", trim($output, "\x1e\n")) as $entry) {
            if (trim($entry) === '') {
                continue;
            }

            [$hash, $author, $committedAt, $subject, $body] = array_pad(
                explode("\x1f", ltrim($entry, "\n"), 5),
                5,
                '',
            );

            $commits->push(new GitCommitData(
                hash: $hash,
                author: $author,
                subject: $subject,
                body: rtrim($body, "\n") ?: null,
                committedAt: CarbonImmutable::parse($committedAt),
            ));
        }

        return $commits;
    }
}
