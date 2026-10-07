<?php

namespace App\Services\Requirements;

use App\Data\Requirements\DecisionBatchResult;
use App\Data\Requirements\DecisionOption;
use App\Enums\RequirementDecider;
use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The 待决策 page's workflow: Gordon picks an answer per 提议/冲突 (kept as a draft), confirms the batch, and what he
 * cannot answer goes on 老板's question list. Confirming writes through RequirementTreeSaver, so its checks, the
 * revisions and the voiding of a replaced rule all apply.
 */
class RequirementDecisions
{
    public function __construct(
        private RequirementTreeSaver $requirementTreeSaver,
        private RequirementTreeService $requirementTreeService,
    ) {}

    public function choose(Requirement $requirement, User $user, string $choice, ?string $customText = null): void
    {
        $customText = trim((string) $customText);

        $error = match (true) {
            ! $requirement->status->awaitsDecision() => "#{$requirement->number} 已经不在等决策了。",
            $choice === RequirementDecisionDraft::CUSTOM && $customText === '' => '自己写的说法不能为空。',
            $choice === RequirementDecisionDraft::ASK_BOSS && $requirement->decider === RequirementDecider::Boss => "#{$requirement->number} 本来就在等老板。",
            ! in_array($choice, RequirementDecisionDraft::RESERVED_CHOICES, true) && $requirement->decisionOrFallback()->option($choice) === null => "#{$requirement->number} 没有选项 {$choice}。",
            default => null,
        };

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        RequirementDecisionDraft::query()->updateOrCreate(
            ['requirement_id' => $requirement->id, 'user_id' => $user->id],
            ['choice' => $choice, 'custom_text' => $choice === RequirementDecisionDraft::CUSTOM ? $customText : null],
        );
    }

    public function clear(Requirement $requirement, User $user): void
    {
        RequirementDecisionDraft::query()->where('requirement_id', $requirement->id)->where('user_id', $user->id)->delete();
    }

    /**
     * Applies $user's drafts on $requirements in one go; the drafts are gone afterwards. Nothing changes if any fails.
     *
     * @param  Collection<int, Requirement>  $requirements
     */
    public function confirm(Project $project, User $user, Collection $requirements): DecisionBatchResult
    {
        $drafts = RequirementDecisionDraft::query()
            ->where('user_id', $user->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->with('requirement.supersedes')
            ->get();
        $nodes = $drafts->map(fn (RequirementDecisionDraft $draft): ?array => $this->nodeFor($draft))->filter()->values()->all();

        DB::transaction(function () use ($project, $nodes, $drafts): void {
            if ($nodes !== []) {
                $this->requirementTreeSaver->save($project, $nodes);
            }

            RequirementDecisionDraft::query()->whereKey($drafts->modelKeys())->delete();
        });

        return DecisionBatchResult::of($drafts);
    }

    /**
     * Everything waiting on 老板, as plain text Gordon can paste to him: the question, now vs. the change, the options
     * and what we recommend.
     */
    public function bossQuestions(Project $project): string
    {
        $questions = $this->requirementTreeService->awaitingDecision($project, $this->requirementTreeService->tree($project))
            ->filter(fn (Requirement $requirement): bool => $requirement->decider === RequirementDecider::Boss)
            ->values();

        $lines = ['需要老板拍板的问题（'.now()->toDateString()."，共 {$questions->count()} 条）"];

        foreach ($questions as $index => $requirement) {
            $lines = [...$lines, '', ...$this->bossQuestion($index + 1, $requirement)];
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function bossQuestion(int $position, Requirement $requirement): array
    {
        $decision = $requirement->decisionOrFallback();
        $recommended = $decision->recommended();

        return array_values(array_filter([
            "{$position}. {$requirement->title}（编号 {$requirement->number}）",
            "   现在：{$decision->now}",
            "   要改成：{$decision->change}",
            $decision->difference ? "   差别：{$decision->difference}" : null,
            $decision->risk ? "   风险：{$decision->risk}" : null,
            '   选项：',
            ...array_map(fn (DecisionOption $option): string => "     {$option->key}. {$option->label} —— {$option->consequence}", $decision->options),
            $recommended ? "   我们推荐：{$recommended->key}. {$recommended->label}" : '   我们推荐：没有倾向，请老板定',
        ]));
    }

    /**
     * The requirements:save node one draft turns into; null when it changes nothing.
     *
     * @return array<string, mixed>|null
     */
    private function nodeFor(RequirementDecisionDraft $draft): ?array
    {
        $requirement = $draft->requirement;

        if (! $requirement->status->awaitsDecision() || $draft->choice === RequirementDecisionDraft::SKIP) {
            return null;
        }

        if ($draft->choice === RequirementDecisionDraft::ASK_BOSS) {
            return ['number' => $requirement->number, 'decider' => RequirementDecider::Boss->value];
        }

        $decider = $requirement->decider === RequirementDecider::Boss ? RequirementDecider::Boss : RequirementDecider::Gordon;
        $today = now()->toDateString();
        $signature = $decider === RequirementDecider::Boss ? "老板拍板 {$today}（经 Gordon 转）" : "Gordon 拍板 {$today}";

        if ($draft->choice === RequirementDecisionDraft::CUSTOM) {
            [$status, $title, $picked, $reason] = [RequirementStatus::Decided, $draft->custom_text, '自己写', "{$decider->value} 自己写"];
        } else {
            $option = $requirement->decisionOrFallback()->option($draft->choice) ?? throw new InvalidArgumentException("#{$requirement->number} 没有选项 {$draft->choice}，重新选一次。");
            $status = $option->outcome->resultingStatus();
            $title = $status === RequirementStatus::Decided ? $option->resultTitle : null;
            [$picked, $reason] = ["选 {$option->key} {$option->label}", "{$option->label}：{$option->consequence}"];
        }

        return array_filter([
            'number' => $requirement->number,
            'status' => $status->value,
            'title' => $title,
            'source' => implode('；', array_filter([$requirement->source, "{$signature}：{$picked}"])),
            'decided_by' => $decider->value,
            'decided_at' => $today,
            'reason' => $reason,
        ], fn (mixed $value): bool => $value !== null);
    }
}
