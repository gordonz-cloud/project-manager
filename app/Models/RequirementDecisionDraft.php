<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one person picked for a 提议/冲突 on the 待决策 page, kept until they confirm the batch.
 * choice is an option key of the node's decision, or one of the reserved choices below.
 *
 * @property int $id
 * @property int $requirement_id
 * @property int $user_id
 * @property string $choice
 * @property string|null $custom_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requirement_id', 'user_id', 'choice', 'custom_text'])]
class RequirementDecisionDraft extends Model
{
    /** Decide it under your own wording. */
    public const CUSTOM = 'custom';

    /** Hand it to 老板. */
    public const ASK_BOSS = 'ask_boss';

    /** Leave it open. */
    public const SKIP = 'skip';

    public const RESERVED_CHOICES = [self::CUSTOM, self::ASK_BOSS, self::SKIP];

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }
}
