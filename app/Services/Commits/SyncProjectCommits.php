<?php

namespace App\Services\Commits;

use App\Data\Commits\CommitSyncResult;
use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Support\Facades\DB;

class SyncProjectCommits
{
    public function __construct(
        private GitLogReader $gitLogReader,
        private RequirementReferenceMatcher $requirementReferenceMatcher,
    ) {}

    public function handle(Project $project, ?string $since = null): CommitSyncResult
    {
        $ruleIdsByNumber = Requirement::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number');

        $existingByHash = Commit::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->withExists('requirements')
            ->get(['id', 'hash', 'subject', 'body', 'author', 'committed_at'])
            ->keyBy('hash');

        $created = 0;
        $updated = 0;
        $autoAssigned = 0;
        $unassigned = 0;
        $now = now();
        $toInsert = [];
        $toUpdate = [];
        /** @var array<string, list<int>> $toLink hash => requirement ids, for commits not linked to any rule yet */
        $toLink = [];

        foreach ($this->gitLogReader->read($project, $since) as $gitCommit) {
            $ruleIds = array_values(array_filter(array_map(
                fn (int $number): ?int => $ruleIdsByNumber[$number] ?? null,
                $this->requirementReferenceMatcher->numbers($gitCommit->subject, $gitCommit->body),
            )));

            if ($ruleIds === []) {
                $unassigned++;
            }

            $existing = $existingByHash->get($gitCommit->hash);

            // Never touch an existing link (manual pick or earlier match); only link a commit that has none.
            if ($ruleIds !== [] && ! $existing?->getAttribute('requirements_exists')) {
                $toLink[$gitCommit->hash] = $ruleIds;
                $autoAssigned++;
            }

            // Match the string the Eloquent 'datetime' cast would produce,
            // so comparing against a value already round-tripped through
            // the DB is a plain string comparison, not a timezone puzzle.
            $committedAt = $gitCommit->committedAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

            if ($existing === null) {
                $created++;

                $toInsert[] = [
                    'project_id' => $project->id,
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

        DB::transaction(function () use ($project, $toInsert, $toUpdate, $toLink) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                Commit::withoutGlobalScopes()->insert($chunk);
            }

            foreach ($toUpdate as $id => $attrs) {
                Commit::withoutGlobalScopes()->whereKey($id)->update($attrs);
            }

            $idByHash = Commit::withoutGlobalScopes()->where('project_id', $project->id)->whereIn('hash', array_keys($toLink))->pluck('id', 'hash');
            $links = [];

            foreach ($toLink as $hash => $ruleIds) {
                foreach ($ruleIds as $ruleId) {
                    $links[] = ['commit_id' => $idByHash[$hash], 'requirement_id' => $ruleId];
                }
            }

            foreach (array_chunk($links, 1000) as $chunk) {
                DB::table('commit_requirement')->insertOrIgnore($chunk);
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
