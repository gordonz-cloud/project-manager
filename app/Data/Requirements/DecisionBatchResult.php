<?php

namespace App\Data\Requirements;

use App\Enums\RequirementStatus;
use App\Models\RequirementDecisionDraft;
use Illuminate\Support\Collection;

/**
 * What a batch of drafts does (shown before confirming) or did (after), by the status each node ends up in.
 */
final readonly class DecisionBatchResult
{
    /**
     * @param  array<string, int>  $byStatus  status value => count, e.g. ['已定' => 2, '作废' => 1]
     * @param  list<string>  $examples  a few drafts in words, e.g. "「游客价格位只显示…」 保持现在"
     */
    public function __construct(public array $byStatus = [], public array $examples = []) {}

    /**
     * @param  Collection<int, RequirementDecisionDraft>  $drafts  with their requirement loaded
     */
    public static function of(Collection $drafts): self
    {
        return new self(
            byStatus: $drafts->countBy(fn (RequirementDecisionDraft $draft): string => $draft->choice === RequirementDecisionDraft::CUSTOM
                ? RequirementStatus::Decided->value
                : ($draft->requirement->decisionOrFallback()->option($draft->choice)?->outcome->resultingStatus()->value ?? '选项已不在'))->all(),
            examples: array_values($drafts->take(3)->map(fn (RequirementDecisionDraft $draft): string => $draft->summary())->all()),
        );
    }

    public function summary(): string
    {
        $labels = [RequirementStatus::Decided->value => '定下', RequirementStatus::Later->value => '以后做', RequirementStatus::Void->value => '作废'];

        return implode(' · ', array_filter([
            ...array_map(fn (string $status, int $count): string => ($labels[$status] ?? $status)." {$count} 条", array_keys($this->byStatus), $this->byStatus),
            isset($this->byStatus[RequirementStatus::Decided->value]) ? '定下的规则从待做开始（原来挂的功能和测试记为受影响）' : null,
        ]));
    }
}
