<?php

namespace App\Services\Commits;

use App\Data\Commits\CommitSyncResult;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        /** @var Collection<string, array{id:int,subject:string,body:?string,author:string,committed_at:string,feature_id:?int}> $existingByHash */
        $existingByHash = Commit::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->get(['id', 'hash', 'subject', 'body', 'author', 'committed_at', 'feature_id'])
            ->keyBy('hash');

        $created = 0;
        $updated = 0;
        $autoAssigned = 0;
        $unassigned = 0;
        $now = now();
        $toInsert = [];
        $toUpdate = [];

        foreach ($this->gitLogReader->read($project, $since) as $gitCommit) {
            $feature = $this->featureReferenceMatcher->match(
                $gitCommit->subject,
                $gitCommit->body,
                $featuresByNumber,
            );

            if ($feature === null) {
                $unassigned++;
            }

            $existing = $existingByHash->get($gitCommit->hash);
            // Match the string the Eloquent 'datetime' cast would produce,
            // so comparing against a value already round-tripped through
            // the DB is a plain string comparison, not a timezone puzzle.
            $committedAt = $gitCommit->committedAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

            if ($existing === null) {
                $created++;
                $autoAssigned += $feature !== null ? 1 : 0;

                $toInsert[] = [
                    'project_id' => $project->id,
                    'feature_id' => $feature?->id,
                    'hash' => $gitCommit->hash,
                    'subject' => $gitCommit->subject,
                    'body' => $gitCommit->body,
                    'author' => $gitCommit->author,
                    'committed_at' => $committedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            $attrs = [
                'subject' => $gitCommit->subject,
                'body' => $gitCommit->body,
                'author' => $gitCommit->author,
                'committed_at' => $committedAt,
            ];

            // Never overwrite an existing feature_id (manual pick or earlier
            // auto-match); only fill it in when it's still null.
            if ($existing->feature_id === null && $feature !== null) {
                $attrs['feature_id'] = $feature->id;
                $autoAssigned++;
            }

            $changed = array_filter($attrs, function ($value, $key) use ($existing) {
                if ($key === 'committed_at') {
                    return $value !== $existing->committed_at->format('Y-m-d H:i:s');
                }

                return $value !== $existing->{$key};
            }, ARRAY_FILTER_USE_BOTH);

            if ($changed !== []) {
                $updated++;
                $changed['updated_at'] = $now;
                $toUpdate[$existing->id] = $changed;
            }
        }

        DB::transaction(function () use ($toInsert, $toUpdate) {
            if ($toInsert !== []) {
                Commit::withoutGlobalScopes()->insert($toInsert);
            }

            foreach ($toUpdate as $id => $attrs) {
                Commit::withoutGlobalScopes()->whereKey($id)->update($attrs);
            }
        });

        return new CommitSyncResult(
            created: $created,
            updated: $updated,
            autoAssigned: $autoAssigned,
            unassigned: $unassigned,
        );
    }
}
