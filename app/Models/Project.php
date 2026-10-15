<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $repo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'repo_path'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * When $user last opened the requirement tree's 最近变化 here; null the first time.
     */
    public function changesSeenBy(User $user): ?Carbon
    {
        $seenAt = $this->users()->withPivot('changes_seen_at')->whereKey($user->id)->first()?->pivot?->getAttribute('changes_seen_at');

        return $seenAt === null ? null : Carbon::parse($seenAt);
    }

    public function markChangesSeen(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, ['changes_seen_at' => now()]);
    }

    /**
     * @return HasMany<Requirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }
}
