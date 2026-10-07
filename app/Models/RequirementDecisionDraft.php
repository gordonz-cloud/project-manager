<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What one person picked for a 提议/冲突 on the 待决策 page, kept until they confirm the batch.
 * choice is one of:
 * - an option key of the node's decision, or "custom" (with custom_text): Gordon decides it;
 * - "opinion:<key|custom>": on a node waiting on 老板, Gordon's opinion to take to the boss (no decision yet);
 * - "boss:<key|custom>": on a node waiting on 老板, the boss's reply as Gordon passes it on;
 * - "ask_boss" (hand it to 老板) or "skip" (leave it open).
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

    /** Hand it to 老板. */
    public const ASK_BOSS = 'ask_boss';

    /** Leave it open. */
    public const SKIP = 'skip';

    /** Prefix of Gordon's opinion on a question for 老板. */
    public const OPINION = 'opinion:';

    /** Prefix of 老板's reply. */
    public const BOSS = 'boss:';

    public const RESERVED_CHOICES = [self::CUSTOM, self::ASK_BOSS, self::SKIP];

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function isOpinion(): bool
    {
        return str_starts_with($this->choice, self::OPINION);
    }

    public function isBossReply(): bool
    {
        return str_starts_with($this->choice, self::BOSS);
    }

    /**
     * Gordon decides it himself: an option or his own wording.
     */
    public function isOwnDecision(): bool
    {
        return ! $this->isOpinion() && ! $this->isBossReply() && ! in_array($this->choice, [self::ASK_BOSS, self::SKIP], true);
    }

    /**
     * The option key the choice points at, "custom" for own wording, null for ask_boss/skip.
     */
    public function optionKey(): ?string
    {
        if (in_array($this->choice, [self::ASK_BOSS, self::SKIP], true)) {
            return null;
        }

        return str_replace([self::OPINION, self::BOSS], '', $this->choice);
    }

    /**
     * The picked answer in words: "A 老板文档已批" or the own wording.
     */
    public function answerLabel(): string
    {
        $key = $this->optionKey();

        return match ($key) {
            null => '',
            self::CUSTOM => "自己写：{$this->custom_text}",
            default => trim("{$key} ".($this->requirement->decisionOrFallback()->option($key)->label ?? '')),
        };
    }

    /**
     * One line for the confirm summary, e.g. "「游客价格位只显示 Market price…」 意见 A 老板文档已批"; no numbers.
     */
    public function summary(): string
    {
        $what = match (true) {
            $this->choice === self::ASK_BOSS => '转老板',
            $this->choice === self::SKIP => '先不定',
            $this->isOpinion() => '意见 '.$this->answerLabel(),
            $this->isBossReply() => '老板回复 '.$this->answerLabel(),
            default => '定 '.$this->answerLabel(),
        };

        return '「'.Str::limit($this->requirement->title, 20, '…')."」 {$what}";
    }
}
