<?php

namespace App\Models;

use App\Enums\DataModelStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\DataModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $table_name
 * @property DataModelStatus $status
 * @property string|null $description
 * @property string|null $business_purpose
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'table_name', 'status', 'description', 'business_purpose'])]
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
     * @return HasMany<ModelField, $this>
     */
    public function modelFields(): HasMany
    {
        return $this->hasMany(ModelField::class);
    }
}
