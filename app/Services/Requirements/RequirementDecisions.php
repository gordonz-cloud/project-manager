<?php

namespace App\Services\Requirements;

use App\Data\Requirements\DecisionBatchResult;
use App\Data\Requirements\DecisionOption;
use App\Enums\RequirementDecider;
use App\Enums\RequirementStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\Test;
use App\Models\User;
use App\Support\RequirementMentions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The 待决策 page's workflow: Gordon picks an answer per 提议/冲突 (kept as a draft) and confirms the batch. On a node
 * waiting on 老板 his pick is an opinion that goes on the boss's question list; the decision comes when he records the
 * boss's reply. Decisions write through RequirementTreeSaver, so its checks, the revisions and the voiding of a replaced
 * rule all apply.
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
        $this->assertFits($requirement, $choice, $customText);

        RequirementDecisionDraft::query()->updateOrCreate(
            ['requirement_id' => $requirement->id, 'user_id' => $user->id],
            ['choice' => $choice, 'custom_text' => str_ends_with($choice, RequirementDecisionDraft::CUSTOM) ? $customText : null],
        );
    }

    /**
     * A choice must suit the node: Gordon decides his own nodes or hands them on; on 老板's he gives an opinion or
     * records the reply.
     */
    private function assertFits(Requirement $requirement, string $choice, string $customText): void
    {
        $draft = new RequirementDecisionDraft(['choice' => $choice]);
        $key = $draft->optionKey();
        $isBossQuestion = $requirement->decider === RequirementDecider::Boss;

        $error = match (true) {
            ! $requirement->status->awaitsDecision() => "#{$requirement->number} 已经不在等决策了。",
            ($draft->isOpinion() || $draft->isBossReply()) && ! $isBossQuestion => "#{$requirement->number} 不在等老板，直接定或转老板。",
            ($draft->isOwnDecision() || $choice === RequirementDecisionDraft::ASK_BOSS) && $isBossQuestion => "#{$requirement->number} 等老板定：选你的意见，或记老板的回复。",
            $key === RequirementDecisionDraft::CUSTOM && $customText === '' => '自己写的说法不能为空。',
            $key !== null && $key !== RequirementDecisionDraft::CUSTOM && $requirement->decisionOrFallback()->option($key) === null => "#{$requirement->number} 没有选项 {$key}。",
            default => null,
        };

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * A 以后做 rule Gordon wants now goes back to 提议, waiting on him.
     */
    public function startNow(Project $project, Requirement $requirement): void
    {
        if ($requirement->status !== RequirementStatus::Later) {
            throw new InvalidArgumentException("#{$requirement->number} 不是以后做。");
        }

        $this->requirementTreeSaver->save($project, [['number' => $requirement->number, 'status' => RequirementStatus::Proposed->value, 'decider' => RequirementDecider::Gordon->value, 'reason' => '现在要做了']]);
    }

    public function clear(Requirement $requirement, User $user): void
    {
        RequirementDecisionDraft::query()->where('requirement_id', $requirement->id)->where('user_id', $user->id)->delete();
    }

    /**
     * What confirming $user's drafts on $requirements would do.
     *
     * @param  Collection<int, Requirement>  $requirements
     */
    public function preview(User $user, Collection $requirements): DecisionBatchResult
    {
        return DecisionBatchResult::of($this->drafts($user, $requirements));
    }

    /**
     * Applies $user's drafts on $requirements in one go; the drafts are gone afterwards. Nothing changes if any fails.
     *
     * @param  Collection<int, Requirement>  $requirements
     */
    public function confirm(Project $project, User $user, Collection $requirements): DecisionBatchResult
    {
        $drafts = $this->drafts($user, $requirements);
        $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->requirement->status->awaitsDecision())
            ->each(fn (RequirementDecisionDraft $draft) => $this->assertFits($draft->requirement, $draft->choice, (string) $draft->custom_text));
        $nodes = $drafts->map(fn (RequirementDecisionDraft $draft): ?array => $this->nodeFor($draft))->filter()->values()->all();
        $opinions = $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->isOpinion() && $draft->requirement->status->awaitsDecision());

        DB::transaction(function () use ($project, $nodes, $drafts, $opinions): void {
            if ($nodes !== []) {
                $this->requirementTreeSaver->save($project, $nodes);
            }

            $opinions->each(fn (RequirementDecisionDraft $draft) => $this->recordOpinion($draft));
            RequirementDecisionDraft::query()->whereKey($drafts->pluck('id'))->delete();
        });

        return DecisionBatchResult::of($drafts);
    }

    /**
     * The questions on the list are now with 老板: they move to 等老板回复.
     */
    public function markSentToBoss(Project $project): int
    {
        return Requirement::withoutGlobalScopes()
            ->whereKey($this->questionsToSend($project)->pluck('id'))
            ->update(['sent_to_boss_at' => now()]);
    }

    /**
     * The questions Gordon has given his opinion on and not sent yet, as plain text he can paste to 老板: the question,
     * now vs. the change, the options, his opinion and why it is the boss's call.
     */
    public function bossQuestions(Project $project): string
    {
        $questions = $this->questionsToSend($project);

        $lines = ['需要老板拍板的问题（'.now()->toDateString()."，共 {$questions->count()} 条）"];

        foreach ($questions as $index => $requirement) {
            $lines = [...$lines, '', ...$this->bossQuestion($index + 1, $requirement)];
        }

        return RequirementMentions::for($project)->plain(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function bossQuestion(int $position, Requirement $requirement): array
    {
        $decision = $requirement->decisionOrFallback();
        $opinion = $requirement->decision_opinion;
        $reason = $decision->option($opinion['key'] ?? '')?->consequence;

        return array_values(array_filter([
            "{$position}. {$requirement->title}",
            $decision->whyBoss ? "   为什么要您定：{$decision->whyBoss}" : null,
            "   现在：{$decision->now}",
            "   要改成：{$decision->change}",
            $decision->difference ? "   差别：{$decision->difference}" : null,
            $decision->risk ? "   风险：{$decision->risk}" : null,
            '   选项：',
            ...array_map(fn (DecisionOption $option): string => "     {$option->key}. {$option->label} —— {$option->consequence}", $decision->options),
            '   Gordon 的意见：'.(($opinion['key'] ?? null) === RequirementDecisionDraft::CUSTOM ? $opinion['label'] : "{$opinion['key']}. {$opinion['label']}").($reason ? "（{$reason}）" : ''),
        ]));
    }

    /**
     * 老板's questions with Gordon's opinion that have not gone out yet, conflicts first, then tree order.
     *
     * @return Collection<int, Requirement>
     */
    private function questionsToSend(Project $project): Collection
    {
        return $this->requirementTreeService->awaitingDecision($project, $this->requirementTreeService->tree($project))
            ->filter(fn (Requirement $requirement): bool => $requirement->decisionStage() === Requirement::STAGE_TO_SEND)
            ->values();
    }

    /**
     * @param  Collection<int, Requirement>  $requirements
     * @return Collection<int, RequirementDecisionDraft>
     */
    private function drafts(User $user, Collection $requirements): Collection
    {
        return RequirementDecisionDraft::query()
            ->where('user_id', $user->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->with(['requirement.supersedes', 'requirement.linkedFeatures', 'requirement.tests'])
            ->get();
    }

    private function recordOpinion(RequirementDecisionDraft $draft): void
    {
        $key = (string) $draft->optionKey();
        $label = $key === RequirementDecisionDraft::CUSTOM ? (string) $draft->custom_text : ($draft->requirement->decisionOrFallback()->option($key)->label ?? throw new InvalidArgumentException("#{$draft->requirement->number} 没有选项 {$key}，重新选一次。"));

        $draft->requirement->update(['decision_opinion' => ['key' => $key, 'label' => $label, 'by' => RequirementDecider::Gordon->value, 'at' => now()->toDateString()]]);
    }

    /**
     * The requirements:save node one draft turns into; null when it changes nothing.
     *
     * @return array<string, mixed>|null
     */
    private function nodeFor(RequirementDecisionDraft $draft): ?array
    {
        $requirement = $draft->requirement;

        if (! $requirement->status->awaitsDecision() || $draft->choice === RequirementDecisionDraft::SKIP || $draft->isOpinion()) {
            return null;
        }

        if ($draft->choice === RequirementDecisionDraft::ASK_BOSS) {
            return ['number' => $requirement->number, 'decider' => RequirementDecider::Boss->value];
        }

        $key = (string) $draft->optionKey();
        $option = $key === RequirementDecisionDraft::CUSTOM ? null : ($requirement->decisionOrFallback()->option($key) ?? throw new InvalidArgumentException("#{$requirement->number} 没有选项 {$key}，重新选一次。"));
        $decider = $draft->isBossReply() ? RequirementDecider::Boss : ($option->recordAs ?? RequirementDecider::Gordon);
        $decidedAt = $option->recordDate ?? now()->toDateString();
        $signature = match (true) {
            $draft->isBossReply() => "老板拍板 {$decidedAt}（经 Gordon 转）",
            $decider === RequirementDecider::Boss => "老板拍板 {$decidedAt}（文档已批）",
            default => "{$decider->value} 拍板 {$decidedAt}",
        };

        if ($option === null) {
            [$status, $title, $picked, $reason] = [RequirementStatus::Decided, "{$requirement->topic()}：{$draft->custom_text}", '自己写', "{$decider->value} 自己写：{$draft->custom_text}"];
        } else {
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
            'decided_at' => $decidedAt,
            'reason' => $reason,
            ...($status === RequirementStatus::Decided ? $this->startedFromScratch($requirement) : []),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A newly decided rule is not built yet, whatever it was linked to while it was only a proposal (often the old
     * behaviour's feature and tests): those links move into decision.impact as 受影响, so it starts as 待做.
     *
     * @return array{decision: array<string, mixed>, features: list<int>, tests: list<int>}|array{}
     */
    private function startedFromScratch(Requirement $requirement): array
    {
        $affected = [
            ...$requirement->linkedFeatures->map(fn (Feature $feature): string => "功能「{$feature->title}」（{$feature->status->value}）"),
            ...$requirement->tests->map(fn (Test $test): string => '测试「'.Str::limit($test->title, 30, '…')."」（{$test->last_result->value}）"),
        ];

        if ($affected === []) {
            return [];
        }

        $decision = $requirement->decisionOrFallback()->toArray();
        $decision['impact'] = implode('；', array_filter([$decision['impact'] ?? null, '受影响（定下前挂着的）：'.implode('、', $affected)]));

        return ['decision' => $decision, 'features' => [], 'tests' => []];
    }
}
