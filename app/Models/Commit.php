<?php

namespace App\Models;

use App\Data\Commits\CommitUpsertResult;
use App\Data\Commits\GitCommitData;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $feature_id
 * @property string $hash
 * @property string $subject
 * @property string|null $body
 * @property string $author
 * @property Carbon $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['hash', 'subject', 'body', 'author', 'committed_at', 'feature_id'])]
class Commit extends Model
{
    /** @use HasFactory<CommitFactory> */
    use BelongsToProject, HasFactory;

    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public static function upsertFromGit(
        Project $project,
        GitCommitData $data,
        ?Feature $feature,
    ): CommitUpsertResult {
        $commit = self::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('hash', $data->hash)
            ->first();
        $created = $commit === null;
        $autoAssigned = false;

        $commit ??= new self;
        $commit->fill([
            'hash' => $data->hash,
            'subject' => $data->subject,
            'body' => $data->body,
            'author' => $data->author,
            'committed_at' => $data->committedAt,
        ]);
        $commit->project_id = $project->id;

        if ($feature !== null && $commit->feature_id === null) {
            $commit->feature_id = $feature->id;
            $autoAssigned = true;
        }

        $commit->save();

        return new CommitUpsertResult(
            commit: $commit,
            created: $created,
            autoAssigned: $autoAssigned,
        );
    }

    public function unassignFeature(): bool
    {
        if ($this->feature_id === null) {
            return false;
        }

        return $this->forceFill(['feature_id' => null])->save();
    }
}
