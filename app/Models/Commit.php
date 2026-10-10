<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $hash
 * @property string $subject
 * @property string|null $body
 * @property string $author
 * @property Carbon $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['hash', 'subject', 'body', 'author', 'committed_at'])]
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
     * Rules this commit works on, from the R<number>s in its message.
     *
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class);
    }
}
