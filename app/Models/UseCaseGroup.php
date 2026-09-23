<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\UseCaseGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int $sort_order
 */
#[Fillable(['name', 'sort_order'])]
class UseCaseGroup extends Model
{
    /** @use HasFactory<UseCaseGroupFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<UseCase, $this>
     */
    public function useCases(): HasMany
    {
        return $this->hasMany(UseCase::class);
    }
}
