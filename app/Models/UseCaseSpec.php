<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\UseCaseSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $use_case_id
 * @property int $version
 * @property string $status
 * @property string $content
 */
#[Fillable(['use_case_id', 'version', 'status', 'content'])]
class UseCaseSpec extends Model
{
    /** @use HasFactory<UseCaseSpecFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $spec): void {
            if (blank($spec->project_id) && filled($spec->use_case_id)) {
                $spec->project_id = UseCase::withoutGlobalScopes()->findOrFail($spec->use_case_id)->project_id;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<UseCase, $this>
     */
    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }
}
