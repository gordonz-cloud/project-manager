<?php

namespace App\Models;

use App\Enums\RequestReplyEdgeKind;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\RequestReplyEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A dependency in a use case's flow graph: after `from` the user goes on to `to`.
 *
 * @property int $id
 * @property int $project_id
 * @property int $from_request_reply_id
 * @property int $to_request_reply_id
 * @property RequestReplyEdgeKind $kind
 * @property string|null $condition
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_request_reply_id', 'to_request_reply_id', 'kind', 'condition'])]
class RequestReplyEdge extends Model
{
    /** @use HasFactory<RequestReplyEdgeFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $edge): void {
            if ((int) $edge->from_request_reply_id === (int) $edge->to_request_reply_id) {
                throw new LogicException('A request reply cannot depend on itself.');
            }

            $ends = RequestReply::withoutGlobalScopes()
                ->whereKey([$edge->from_request_reply_id, $edge->to_request_reply_id])
                ->get(['use_case_id', 'project_id']);

            if ($ends->count() !== 2 || $ends->pluck('use_case_id')->unique()->count() !== 1) {
                throw new LogicException('Both ends of a flow edge must belong to the same use case.');
            }

            $edge->project_id = (int) $ends->first()?->project_id;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RequestReplyEdgeKind::class,
        ];
    }

    /**
     * @return BelongsTo<RequestReply, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(RequestReply::class, 'from_request_reply_id');
    }

    /**
     * @return BelongsTo<RequestReply, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(RequestReply::class, 'to_request_reply_id');
    }
}
