<?php

namespace App\Models;

use App\Enums\DataModelStatus;
use App\Models\Concerns\BelongsToProject;
use App\Models\Concerns\HasProjectSequence;
use Database\Factories\ModelFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $data_model_id
 * @property int $number
 * @property string $name
 * @property string|null $type
 * @property bool $nullable
 * @property string|null $default_value
 * @property string|null $constraint
 * @property string|null $description
 * @property DataModelStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['data_model_id', 'number', 'name', 'type', 'nullable', 'default_value', 'constraint', 'description', 'status'])]
class ModelField extends Model
{
    /** @use HasFactory<ModelFieldFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nullable' => 'boolean',
            'status' => DataModelStatus::class,
        ];
    }

    /**
     * @return BelongsTo<DataModel, $this>
     */
    public function dataModel(): BelongsTo
    {
        return $this->belongsTo(DataModel::class);
    }
}
