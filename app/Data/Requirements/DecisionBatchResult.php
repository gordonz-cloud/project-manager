<?php

namespace App\Data\Requirements;

use App\Models\RequirementDecisionDraft;
use Illuminate\Support\Collection;

/**
 * What a batch of drafts does (shown before confirming) or did (after), by kind of choice.
 */
final readonly class DecisionBatchResult
{
    /**
     * @param  list<string>  $examples  a few drafts in words, e.g. "#638 意见 A 老板文档已批"
     */
    public function __construct(
        public int $decidedByMe = 0,
        public int $decidedByBoss = 0,
        public int $opinions = 0,
        public int $forwarded = 0,
        public int $skipped = 0,
        public array $examples = [],
    ) {}

    /**
     * @param  Collection<int, RequirementDecisionDraft>  $drafts  with their requirement loaded
     */
    public static function of(Collection $drafts): self
    {
        return new self(
            decidedByMe: $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->isOwnDecision())->count(),
            decidedByBoss: $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->isBossReply())->count(),
            opinions: $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->isOpinion())->count(),
            forwarded: $drafts->where('choice', RequirementDecisionDraft::ASK_BOSS)->count(),
            skipped: $drafts->where('choice', RequirementDecisionDraft::SKIP)->count(),
            examples: array_values($drafts->take(3)->map(fn (RequirementDecisionDraft $draft): string => $draft->summary())->all()),
        );
    }

    public function decided(): int
    {
        return $this->decidedByMe + $this->decidedByBoss;
    }

    public function summary(): string
    {
        return implode(' · ', array_filter([
            "定下 {$this->decided()} 条（你拍板 {$this->decidedByMe}，按老板回复 {$this->decidedByBoss}）",
            "加入问老板清单 {$this->opinions} 条",
            $this->forwarded ? "转老板 {$this->forwarded} 条" : null,
            $this->skipped ? "先不定 {$this->skipped} 条" : null,
        ]));
    }
}
