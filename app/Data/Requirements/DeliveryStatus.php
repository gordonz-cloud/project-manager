<?php

namespace App\Data\Requirements;

use App\Models\Requirement;

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
     * test). Only live tests count (过时/停用 are no evidence). Expects Requirement::withDeliveryCounts().
     */
    public static function of(Requirement $requirement): ?self
    {
        $count = fn (string $attribute): int => (int) $requirement->getAttribute($attribute);
        $tests = $count('live_tests_count');
        $needsReview = $requirement->needs_review || $count('automated_tests_count') < $tests;

        return match (true) {
            $count('failed_tests_count') > 0 => self::Failed,
            $requirement->accepted_at !== null => self::Verified,
            $tests > 0 && $count('passed_automated_tests_count') === $count('automated_tests_count') => $needsReview ? self::AwaitingAcceptance : self::Verified,
            (bool) $requirement->getAttribute('commits_exists') || $count('passed_tests_count') > 0 => self::InProgress,
            $tests > 0 => self::NotBuilt,
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
