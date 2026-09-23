<?php

namespace App\Models;

use App\Enums\NodeRunMode;
use App\Enums\NodeRunStatus;
use Database\Factories\NodeRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $workflow_run_id
 * @property int $implementation_node_id
 * @property NodeRunMode $mode
 * @property NodeRunStatus $status
 * @property array<string, mixed> $contract_snapshot
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workflow_run_id', 'implementation_node_id', 'mode', 'status', 'contract_snapshot', 'payload', 'started_at', 'finished_at'])]
class NodeRun extends Model
{
    /** @use HasFactory<NodeRunFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $nodeRun): void {
            if (
                $nodeRun->exists
                && $nodeRun->events()->exists()
                && $nodeRun->isDirty(['implementation_node_id', 'mode', 'contract_snapshot'])
            ) {
                throw new LogicException('A node run with events cannot change its identity or contract.');
            }

            $run = WorkflowRun::withoutGlobalScopes()->find($nodeRun->workflow_run_id);
            $node = ImplementationNode::withoutGlobalScopes()->find($nodeRun->implementation_node_id);

            if ($run === null || $node === null) {
                throw new LogicException('A node run must reference an existing workflow and implementation node.');
            }

            if ($run->project_id !== $node->project_id) {
                throw new LogicException('A node run workflow and node must belong to the same project.');
            }

            if ($run->feature_id !== null && $run->feature_id !== $node->feature_id) {
                throw new LogicException('A node run node must belong to the workflow feature.');
            }

            if ($run->feature_id === null) {
                $nodeFeature = $node->feature()->first();

                if ($run->use_case_id !== null && $nodeFeature?->use_case_id !== $run->use_case_id) {
                    throw new LogicException('A node run node must belong to the workflow use case.');
                }

                if (
                    $run->use_case_id === null
                    && $nodeFeature?->requirement_id !== $run->requirement_id
                ) {
                    throw new LogicException('A node run node must belong to the workflow requirement.');
                }
            }
        });

        static::deleting(function (self $nodeRun): void {
            if ($nodeRun->events()->exists()) {
                throw new LogicException('Node runs with events are append-only.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => NodeRunMode::class,
            'status' => NodeRunStatus::class,
            'contract_snapshot' => 'array',
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function implementationNode(): BelongsTo
    {
        return $this->belongsTo(ImplementationNode::class);
    }

    /**
     * @return HasMany<RunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RunEvent::class);
    }
}
