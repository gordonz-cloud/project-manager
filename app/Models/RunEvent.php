<?php

namespace App\Models;

use App\Enums\RunEventType;
use Carbon\CarbonImmutable;
use Database\Factories\RunEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $workflow_run_id
 * @property RunEventType $event_type
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable $created_at
 */
#[Fillable(['workflow_run_id', 'event_type', 'payload', 'created_at'])]
class RunEvent extends Model
{
    /** @use HasFactory<RunEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->created_at ??= now();
        });

        static::updating(fn (): never => throw new LogicException('Run events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Run events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => RunEventType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
