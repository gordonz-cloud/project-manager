<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\ModuleSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int $module_id
 * @property int $version
 * @property string $status
 * @property string|null $summary
 * @property string|null $content
 */
#[Fillable(['module_id', 'version', 'status', 'summary', 'content'])]
class ModuleSpec extends Model
{
    /** @use HasFactory<ModuleSpecFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $spec): void {
            if (blank($spec->module_id)) {
                return;
            }

            $module = Module::withoutGlobalScopes()->find($spec->module_id);

            if ($module === null) {
                throw new LogicException('A module spec must belong to an existing module.');
            }

            if (blank($spec->project_id)) {
                $spec->project_id = $module->project_id;
            } elseif ((int) $spec->project_id !== $module->project_id) {
                throw new LogicException('A module spec must belong to the same project as its module.');
            }
        });

        static::deleting(function (self $spec): void {
            if ($spec->useCases()->exists()) {
                throw new LogicException('A module spec attached to use cases cannot be deleted.');
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
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsToMany<UseCase, $this>
     */
    public function useCases(): BelongsToMany
    {
        return $this->belongsToMany(UseCase::class, 'module_use_cases', 'module_id', 'use_case_id', 'module_id');
    }
}
