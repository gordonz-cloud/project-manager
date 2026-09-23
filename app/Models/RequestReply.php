<?php

namespace App\Models;

use App\Enums\FeatureTrigger;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\RequestReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One request→response cycle of a use case: a route, command, webhook, scheduled or queued job.
 *
 * @property int $id
 * @property int $project_id
 * @property int $use_case_id
 * @property FeatureTrigger $trigger
 * @property string|null $method
 * @property string $entry
 * @property string $title
 * @property int|null $module_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['use_case_id', 'trigger', 'method', 'entry', 'title', 'module_id', 'sort_order'])]
class RequestReply extends Model
{
    /** @use HasFactory<RequestReplyFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $requestReply): void {
            $useCaseProjectId = UseCase::withoutGlobalScopes()->whereKey($requestReply->use_case_id)->value('project_id');

            if ($useCaseProjectId === null) {
                throw new LogicException('A request reply use case must exist.');
            }

            if (blank($requestReply->project_id)) {
                $requestReply->project_id = (int) $useCaseProjectId;
            } elseif ((int) $requestReply->project_id !== (int) $useCaseProjectId) {
                throw new LogicException('A request reply use case must belong to the same project.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => FeatureTrigger::class,
        ];
    }

    /**
     * "POST /login", or the bare entry for a command or job.
     */
    public function label(): string
    {
        return trim("{$this->method} {$this->entry}");
    }

    /**
     * @return BelongsTo<UseCase, $this>
     */
    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsToMany<Feature, $this, FeatureRequestReply>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class)->using(FeatureRequestReply::class);
    }

    /**
     * @return HasMany<RequestReplyEdge, $this>
     */
    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(RequestReplyEdge::class, 'from_request_reply_id');
    }

    /**
     * @return HasMany<RequestReplyEdge, $this>
     */
    public function incomingEdges(): HasMany
    {
        return $this->hasMany(RequestReplyEdge::class, 'to_request_reply_id');
    }
}
