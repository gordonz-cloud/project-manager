<?php

namespace App\Data\Requirements;

use App\Models\RequirementDecisionDraft;
use Illuminate\Support\Collection;

/**
 * What confirming a batch of drafts did, by kind of choice.
 */
final readonly class DecisionBatchResult
{
    public function __construct(
        public int $decided = 0,
        public int $forwarded = 0,
        public int $skipped = 0,
    ) {}

    /**
     * @param  Collection<int, RequirementDecisionDraft>  $drafts  the applied drafts
     */
    public static function of(Collection $drafts): self
    {
        $forwarded = $drafts->where('choice', RequirementDecisionDraft::ASK_BOSS)->count();
        $skipped = $drafts->where('choice', RequirementDecisionDraft::SKIP)->count();

        return new self($drafts->count() - $forwarded - $skipped, $forwarded, $skipped);
    }

    public function summary(): string
    {
        return "定了 {$this->decided} 条，转老板 {$this->forwarded} 条，先不定 {$this->skipped} 条";
    }
}
