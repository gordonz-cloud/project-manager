<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LogicException;

/**
 * @property int $feature_id
 * @property int $request_reply_id
 */
class FeatureRequestReply extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $featureProjectId = Feature::withoutGlobalScopes()->whereKey($link->feature_id)->value('project_id');
            $requestReplyProjectId = RequestReply::withoutGlobalScopes()->whereKey($link->request_reply_id)->value('project_id');

            if ($featureProjectId === null || (int) $featureProjectId !== (int) $requestReplyProjectId) {
                throw new LogicException('A feature and its request reply must belong to the same project.');
            }
        });
    }
}
