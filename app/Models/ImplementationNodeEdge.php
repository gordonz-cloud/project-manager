<?php

namespace App\Models;

use App\Enums\ImplementationNodeEdgeKind;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\ImplementationNodeEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $from_node_id
 * @property int $to_node_id
 * @property ImplementationNodeEdgeKind $kind
 * @property string|null $condition
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_node_id', 'to_node_id', 'kind', 'condition'])]
class ImplementationNodeEdge extends Model
{
    /** @use HasFactory<ImplementationNodeEdgeFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ImplementationNodeEdgeKind::class,
        ];
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(ImplementationNode::class, 'from_node_id');
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function toNode(): BelongsTo
    {
        return $this->belongsTo(ImplementationNode::class, 'to_node_id');
    }
}
