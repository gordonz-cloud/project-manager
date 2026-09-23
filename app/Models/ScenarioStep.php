<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $scenario_id
 * @property int $position
 * @property int $request_reply_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scenario_id', 'position', 'request_reply_id'])]
class ScenarioStep extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $step): void {
            $scenarioUseCaseId = Scenario::withoutGlobalScopes()->whereKey($step->scenario_id)->value('use_case_id');
            $requestReplyUseCaseId = RequestReply::withoutGlobalScopes()->whereKey($step->request_reply_id)->value('use_case_id');

            if ($scenarioUseCaseId === null || (int) $scenarioUseCaseId !== (int) $requestReplyUseCaseId) {
                throw new LogicException('A scenario step must be a request reply of the scenario use case.');
            }
        });
    }

    /**
     * @return BelongsTo<Scenario, $this>
     */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /**
     * @return BelongsTo<RequestReply, $this>
     */
    public function requestReply(): BelongsTo
    {
        return $this->belongsTo(RequestReply::class);
    }
}
