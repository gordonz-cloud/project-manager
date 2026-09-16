<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $notion_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'notion_url'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class);
    }

    /**
     * @return BelongsToMany<DataModel, $this>
     */
    public function dataModels(): BelongsToMany
    {
        return $this->belongsToMany(DataModel::class);
    }
}
