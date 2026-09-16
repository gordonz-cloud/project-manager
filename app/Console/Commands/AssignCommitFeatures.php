<?php

namespace App\Console\Commands;

use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $spec = File::exists($path) ? json_decode(File::get($path), true) : null;

        if (! is_array($spec) || ! isset($spec['project']) || ! is_array($spec['assignments'] ?? null)) {
            $this->error("Expected {project, assignments[]} in {$path}");

            return self::FAILURE;
        }

        $project = Project::query()->where('slug', $spec['project'])->first();

        if ($project === null) {
            $this->error("No project {$spec['project']}");

            return self::FAILURE;
        }

        $features = Feature::query()->where('project_id', $project->id)->pluck('id', 'number');
        $assigned = 0;
        $kept = 0;
        $problems = [];

        foreach ($spec['assignments'] as $row) {
            $hash = (string) ($row['hash'] ?? '');
            $number = (int) ($row['feature'] ?? 0);
            $featureId = $features[$number] ?? null;

            if ($featureId === null) {
                $problems[] = "{$hash}: no feature {$number}";

                continue;
            }

            $matches = Commit::query()->where('project_id', $project->id)->where('hash', 'like', $hash.'%')->get();

            if ($matches->count() !== 1) {
                $problems[] = "{$hash}: matches {$matches->count()} commits";

                continue;
            }

            $commit = $matches->first();

            if ($commit->feature_id !== null) {
                $kept++;

                continue;
            }

            $commit->feature_id = $featureId;
            $commit->save();
            $assigned++;
        }

        $this->info("assigned {$assigned}, already assigned {$kept}, problems ".count($problems));

        foreach ($problems as $problem) {
            $this->line('  '.$problem);
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
