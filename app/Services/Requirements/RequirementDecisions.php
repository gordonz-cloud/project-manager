<?php

namespace App\Services\Requirements;

use App\Data\Requirements\DecisionBatchResult;
use App\Enums\RequirementStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The 待决策 workflow: Gordon picks an answer per 提议/冲突 (kept as a draft) and confirms the batch; every decision is
 * his. Decisions write through RequirementTreeSaver, so its checks, the revisions and the voiding of a replaced rule all
 * apply.
 */
class RequirementDecisions
{
    public function __construct(private RequirementTreeSaver $requirementTreeSaver) {}

    public function choose(Requirement $requirement, User $user, string $choice, ?string $customText = null): void
    {
        $customText = trim((string) $customText);
        $this->assertFits($requirement, $choice, $customText);

        RequirementDecisionDraft::query()->updateOrCreate(
            ['requirement_id' => $requirement->id, 'user_id' => $user->id],
            ['choice' => $choice, 'custom_text' => $choice === RequirementDecisionDraft::CUSTOM ? $customText : null],
        );
    }

    private function assertFits(Requirement $requirement, string $choice, string $customText): void
    {
        $error = match (true) {
            ! $requirement->status->awaitsDecision() => "#{$requirement->number} 已经不在等决策了。",
            $choice === RequirementDecisionDraft::CUSTOM => $customText === '' ? '自己写的说法不能为空。' : null,
            $requirement->decisionOrFallback()->option($choice) === null => "#{$requirement->number} 没有选项 {$choice}。",
            default => null,
        };

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * A 以后做 rule Gordon wants now goes back to 提议.
     */
    public function startNow(Project $project, Requirement $requirement): void
    {
        if ($requirement->status !== RequirementStatus::Later) {
            throw new InvalidArgumentException("#{$requirement->number} 不是以后做。");
        }

        $this->requirementTreeSaver->save($project, [['number' => $requirement->number, 'status' => RequirementStatus::Proposed->value, 'reason' => '现在要做了']]);
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
        $pending = $drafts->filter(fn (RequirementDecisionDraft $draft): bool => $draft->requirement->status->awaitsDecision());
        $pending->each(fn (RequirementDecisionDraft $draft) => $this->assertFits($draft->requirement, $draft->choice, (string) $draft->custom_text));
        $nodes = $pending->map(fn (RequirementDecisionDraft $draft): array => $this->nodeFor($draft))->values()->all();

        DB::transaction(function () use ($project, $nodes, $drafts): void {
            if ($nodes !== []) {
                $this->requirementTreeSaver->save($project, $nodes);
            }

            RequirementDecisionDraft::query()->whereKey($drafts->pluck('id'))->delete();
        });

        return DecisionBatchResult::of($pending);
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

    /**
     * The requirements:save node one draft turns into, signed as Gordon's decision of today.
     *
     * @return array<string, mixed>
     */
    private function nodeFor(RequirementDecisionDraft $draft): array
    {
        $requirement = $draft->requirement;
        $today = now()->toDateString();

        if ($draft->choice === RequirementDecisionDraft::CUSTOM) {
            [$status, $title, $picked, $reason] = [RequirementStatus::Decided, "{$requirement->topic()}：{$draft->custom_text}", "自己写：{$draft->custom_text}", "Gordon 自己写：{$draft->custom_text}"];
        } else {
            $option = $requirement->decisionOrFallback()->option($draft->choice) ?? throw new InvalidArgumentException("#{$requirement->number} 没有选项 {$draft->choice}，重新选一次。");
            $status = $option->outcome->resultingStatus();
            $title = $status === RequirementStatus::Decided ? $option->resultTitle : null;
            [$picked, $reason] = [$option->label, "{$option->label}：{$option->consequence}"];
        }

        return array_filter([
            'number' => $requirement->number,
            'status' => $status->value,
            'title' => $title,
            'source' => implode('；', array_filter([$requirement->source, "Gordon 拍板 {$today}：{$picked}"])),
            'decided_by' => 'Gordon',
            'decided_at' => $today,
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
