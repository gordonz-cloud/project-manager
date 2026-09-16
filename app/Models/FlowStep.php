<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\FlowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $feature_id
 * @property string $path
 * @property int $order
 * @property string $step
 * @property string|null $location
 * @property string|null $input
 * @property string|null $change
 * @property string|null $output
 * @property int|null $input_shape_id
 * @property int|null $output_shape_id
 * @property string|null $notion_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['feature_id', 'path', 'order', 'step', 'location', 'input', 'change', 'output', 'input_shape_id', 'output_shape_id', 'notion_url'])]
class FlowStep extends Model
{
    /** @use HasFactory<FlowStepFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return BelongsTo<DataShape, $this>
     */
    public function inputShape(): BelongsTo
    {
        return $this->belongsTo(DataShape::class, 'input_shape_id');
    }

    /**
     * @return BelongsTo<DataShape, $this>
     */
    public function outputShape(): BelongsTo
    {
        return $this->belongsTo(DataShape::class, 'output_shape_id');
    }
}
