<?php

namespace App\Data\Requirements;

use App\Enums\FeatureStatus;
use App\Enums\TestLastResult;
use App\Models\Feature;
use App\Models\Test;
use Illuminate\Support\Collection;

/**
 * How far a requirement is delivered. Never stored: derived from its linked features and verifying tests,
 * and for a parent from its decided children.
 */
enum DeliveryStatus: string
{
    case Failed = '验证失败';
    case NotBuilt = '未实现';
    case InProgress = '实现中';
    case Built = '已实现';
    case Verified = '已验证';

    /**
     * What the node's own links say; null when nothing is linked.
     *
     * @param  Collection<int, Feature>  $features
     * @param  Collection<int, Test>  $tests
     */
    public static function fromLinks(Collection $features, Collection $tests): ?self
    {
        $features = $features->reject(fn (Feature $feature): bool => $feature->status === FeatureStatus::Void);
        $results = $tests->map(fn (Test $test): TestLastResult => $test->last_result);

        return match (true) {
            $results->contains(TestLastResult::Failed) => self::Failed,
            $results->isNotEmpty() && $results->every(fn (TestLastResult $result): bool => $result === TestLastResult::Passed) => self::Verified,
            $features->isNotEmpty() && $features->every(fn (Feature $feature): bool => $feature->status === FeatureStatus::Done) => self::Built,
            $features->contains(fn (Feature $feature): bool => in_array($feature->status, [FeatureStatus::InDevelopment, FeatureStatus::InVerification, FeatureStatus::Done], true)) => self::InProgress,
            $features->isNotEmpty() || $tests->isNotEmpty() => self::NotBuilt,
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

    public function color(): string
    {
        return match ($this) {
            self::Failed => 'text-red-500',
            self::NotBuilt => 'text-slate-400',
            self::InProgress => 'text-amber-500',
            self::Built => 'text-sky-500',
            self::Verified => 'text-emerald-500',
        };
    }

    private function rank(): int
    {
        return match ($this) {
            self::Failed => -1,
            self::NotBuilt => 0,
            self::InProgress => 1,
            self::Built => 2,
            self::Verified => 3,
        };
    }
}
