<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\DataShapeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $format
 * @property string $sample
 * @property int|null $data_model_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'format', 'sample', 'data_model_id', 'notes'])]
class DataShape extends Model
{
    /** @use HasFactory<DataShapeFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<DataModel, $this>
     */
    public function dataModel(): BelongsTo
    {
        return $this->belongsTo(DataModel::class);
    }

    /**
     * @return HasMany<FlowStep, $this>
     */
    public function inputOf(): HasMany
    {
        return $this->hasMany(FlowStep::class, 'input_shape_id');
    }

    /**
     * @return HasMany<FlowStep, $this>
     */
    public function outputOf(): HasMany
    {
        return $this->hasMany(FlowStep::class, 'output_shape_id');
    }
}
