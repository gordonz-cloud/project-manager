<?php

namespace App\Console\Commands;

use App\Services\Commits\AssignCommitsToFeatures;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Hangs commits on features from a JSON map: {"project": "sg",
 * "assignments": [{"hash": "0b1147a1", "feature": 66}, ...]}. The map is
 * written by whoever read the commits and the feature list side by side —
 * the one-off pass over history that commit messages carrying "Feature N"
 * make unnecessary from here on. A commit already hanging somewhere is left
 * alone; hash prefixes are accepted when they match exactly one commit.
 */
class AssignCommitFeatures extends Command
{
    protected $signature = 'commits:assign {file : JSON map of hash → feature number}';

    protected $description = 'Hang commits on features from a JSON map, never overwriting an existing assignment';

    public function handle(AssignCommitsToFeatures $assignCommitsToFeatures): int
    {
        $file = (string) $this->argument('file');

        try {
            $result = $assignCommitsToFeatures->handle($file);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        foreach ($result->problems as $problem) {
            $this->line('  '.$problem);
        }

        return $result->hasProblems() ? self::FAILURE : self::SUCCESS;
    }
}
