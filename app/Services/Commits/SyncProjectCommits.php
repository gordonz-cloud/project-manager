<?php

namespace App\Services\Commits;

use App\Data\Commits\CommitSyncResult;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;

class SyncProjectCommits
{
    public function __construct(
        private GitLogReader $gitLogReader,
        private FeatureReferenceMatcher $featureReferenceMatcher,
    ) {}

    public function handle(Project $project, ?string $since = null): CommitSyncResult
    {
        $featuresByNumber = Feature::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('number');

        $created = 0;
        $updated = 0;
        $autoAssigned = 0;
        $unassigned = 0;

        foreach ($this->gitLogReader->read($project, $since) as $gitCommit) {
            $feature = $this->featureReferenceMatcher->match(
                $gitCommit->subject,
                $gitCommit->body,
                $featuresByNumber,
            );
            $result = Commit::upsertFromGit($project, $gitCommit, $feature);

            $created += $result->created ? 1 : 0;
            $updated += $result->created ? 0 : 1;
            $autoAssigned += $result->autoAssigned ? 1 : 0;
            $unassigned += $feature === null ? 1 : 0;
        }

        return new CommitSyncResult(
            created: $created,
            updated: $updated,
            autoAssigned: $autoAssigned,
            unassigned: $unassigned,
        );
    }
}
