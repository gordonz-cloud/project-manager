<?php

namespace App\Models;

use App\Enums\DataModelStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\DataModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $table_name
 * @property DataModelStatus $status
 * @property string|null $description
 * @property string|null $business_purpose
 * @property string|null $notion_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'table_name', 'status', 'description', 'business_purpose', 'notion_url'])]
class DataModel extends Model
{
    /** @use HasFactory<DataModelFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DataModelStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    /**
     * @return HasMany<ModelField, $this>
     */
    public function modelFields(): HasMany
    {
        return $this->hasMany(ModelField::class);
    }

    /**
     * A model has no module of its own; it inherits the modules of the
     * requirements its features serve. Derived, not stored, so it can never
     * drift from the features → requirement → module chain that already
     * carries the fact. Call with `features.requirement.modules` eager
     * loaded to avoid N+1 queries.
     *
     * @return Collection<int, Module>
     */
    public function derivedModules(): Collection
    {
        return $this->features
            ->loadMissing('requirement.modules')
            ->filter(fn (Feature $feature): bool => $feature->requirement !== null)
            ->flatMap(fn (Feature $feature): Collection => $feature->requirement->modules)
            ->unique('id')
            ->values();
    }
}
