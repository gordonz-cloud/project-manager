<?php

namespace App\Models;

use App\Data\Requirements\RequirementDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What one person picked for a 提议/冲突, kept until they confirm the batch: a choice key of the node's decision
 * (保持现在 and 以后做 included), or "custom" with custom_text for their own wording. No draft = not decided yet.
 *
 * @property int $id
 * @property int $requirement_id
 * @property int $user_id
 * @property string $choice
 * @property string|null $custom_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Requirement $requirement
 */
#[Fillable(['requirement_id', 'user_id', 'choice', 'custom_text'])]
class RequirementDecisionDraft extends Model
{
    /** Decide it under your own wording. */
    public const CUSTOM = 'custom';

    public const RESERVED_CHOICES = [self::CUSTOM, RequirementDecision::KEEP, RequirementDecision::LATER];

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * The picked answer in words: the option's label or the own wording.
     */
    public function answerLabel(): string
    {
        return $this->choice === self::CUSTOM
            ? "自己写：{$this->custom_text}"
            : (string) $this->requirement->decisionOrFallback()->option($this->choice)?->label;
    }

    /**
     * One line for the confirm summary, e.g. "「游客价格位只显示 Market price…」 保持现在"; no numbers.
     */
    public function summary(): string
    {
        return '「'.Str::limit($this->requirement->title, 20, '…')."」 {$this->answerLabel()}";
    }
}
