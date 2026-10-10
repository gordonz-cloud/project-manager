<?php

namespace App\Data\Requirements;

use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Models\Requirement;
use App\Models\Test;

/**
 * How far a requirement is delivered. Never stored: derived from its own tests, commits and acceptance,
 * and for a parent from its decided children.
 */
enum DeliveryStatus: string
{
    case Failed = '验证失败';
    case NotBuilt = '未实现';
    case InProgress = '实现中';
    case AwaitingAcceptance = '待验收';
    case Verified = '已验证';

    /**
     * What the node's own links say; null when nothing is linked. A failing test always wins; an accepted node is done;
     * once every automated test passes it is done, or 待验收 while Gordon still has to look (needs_review, or a manual
     * test). Expects tests (with last_result, auto) loaded and commits_exists from withExists('commits').
     */
    public static function of(Requirement $requirement): ?self
    {
        $tests = $requirement->tests;
        $automated = $tests->reject(fn (Test $test): bool => $test->auto === TestAuto::No);
        $needsReview = $requirement->needs_review || $automated->count() < $tests->count();
        $passed = fn (Test $test): bool => $test->last_result === TestLastResult::Passed;

        return match (true) {
            $tests->contains(fn (Test $test): bool => $test->last_result === TestLastResult::Failed) => self::Failed,
            $requirement->accepted_at !== null => self::Verified,
            $tests->isNotEmpty() && $automated->every($passed) => $needsReview ? self::AwaitingAcceptance : self::Verified,
            (bool) $requirement->getAttribute('commits_exists') || $tests->contains($passed) => self::InProgress,
            $tests->isNotEmpty() => self::NotBuilt,
            default => null,
        };
    }

    /**
     * One status for several: a failure anywhere wins, otherwise the least delivered, and a mix of
     * built and unbuilt reads as in progress.
     *
     * @param  list<self>  $statuses
     */
    public static function combined(array $statuses): self
    {
        if ($statuses === []) {
            return self::NotBuilt;
        }

        if (in_array(self::Failed, $statuses, true)) {
            return self::Failed;
        }

        $least = array_reduce($statuses, fn (self $carry, self $status): self => $status->rank() < $carry->rank() ? $status : $carry, $statuses[0]);
        $isMixed = array_filter($statuses, fn (self $status): bool => $status !== $least) !== [];

        return $least === self::NotBuilt && $isMixed ? self::InProgress : $least;
    }

    private function rank(): int
    {
        return match ($this) {
            self::Failed => -1,
            self::NotBuilt => 0,
            self::InProgress => 1,
            self::AwaitingAcceptance => 2,
            self::Verified => 3,
        };
    }
}
