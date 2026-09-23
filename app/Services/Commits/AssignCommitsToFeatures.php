<?php

namespace App\Services\Commits;

use App\Data\Commits\CommitFeatureAssignmentResult;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class AssignCommitsToFeatures
{
    public function handle(string $file): CommitFeatureAssignmentResult
    {
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project']) || ! is_array($spec['assignments'] ?? null)) {
            throw new InvalidArgumentException("Expected {project, assignments[]} in {$file}");
        }

        $project = Project::query()->where('slug', $spec['project'])->first();

        if ($project === null) {
            throw new InvalidArgumentException("No project {$spec['project']}");
        }

        $features = Feature::query()->where('project_id', $project->id)->pluck('id', 'number');
        $assigned = 0;
        $alreadyAssigned = 0;
        $problems = [];

        foreach ($spec['assignments'] as $row) {
            $hash = (string) ($row['hash'] ?? '');
            $number = (int) ($row['feature'] ?? 0);
            $featureId = $features[$number] ?? null;

            if ($featureId === null) {
                $problems[] = "{$hash}: no feature {$number}";

                continue;
            }

            $matches = Commit::query()
                ->where('project_id', $project->id)
                ->where('hash', 'like', $hash.'%')
                ->get();

            if ($matches->count() !== 1) {
                $problems[] = "{$hash}: matches {$matches->count()} commits";

                continue;
            }

            $commit = $matches->first();

            if ($commit->feature_id !== null) {
                $alreadyAssigned++;

                continue;
            }

            $commit->feature_id = $featureId;
            $commit->save();
            $assigned++;
        }

        return new CommitFeatureAssignmentResult(
            assigned: $assigned,
            alreadyAssigned: $alreadyAssigned,
            problems: $problems,
        );
    }
}
