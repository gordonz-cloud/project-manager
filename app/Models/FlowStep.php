<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\FlowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int $feature_id
 * @property int|null $implementation_node_id
 * @property string $path
 * @property int $order
 * @property string $step
 * @property string|null $file
 * @property string|null $function
 * @property string|null $input
 * @property string|null $change
 * @property string|null $output
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['feature_id', 'implementation_node_id', 'path', 'order', 'step', 'file', 'function', 'input', 'change', 'output'])]
class FlowStep extends Model
{
    /** @use HasFactory<FlowStepFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $step): void {
            if ($step->implementation_node_id === null) {
                return;
            }

            $node = ImplementationNode::withoutGlobalScopes()->find($step->implementation_node_id);

            if ($node === null || $node->feature_id !== $step->feature_id) {
                throw new LogicException('A flow step implementation node must belong to the same feature.');
            }

            $projectId = $step->getRawOriginal('project_id');

            if ($projectId === null) {
                $step->project_id = $node->project_id;
            } elseif ((int) $projectId !== $node->project_id) {
                throw new LogicException('A flow step implementation node must belong to the same project.');
            }
        });
    }

    /**
     * @param  Builder<FlowStep>  $query
     * @return Builder<FlowStep>
     */
    #[Scope]
    protected function forFeature(Builder $query, ?int $featureId): Builder
    {
        return $query->where('feature_id', $featureId);
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function implementationNode(): BelongsTo
    {
        return $this->belongsTo(ImplementationNode::class);
    }
}
