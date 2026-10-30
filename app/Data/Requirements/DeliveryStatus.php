<?php

namespace App\Data\Requirements;

use App\Models\Requirement;

/** Delivery is derived from commits and Gordon's acceptance, never stored. */
enum DeliveryStatus: string
{
    case NotBuilt = '未实现';
    case AwaitingAcceptance = '待验收';
    case Verified = '已验证';

    /** Expects Requirement::withDeliveryCounts(). No evidence returns null. */
    public static function of(Requirement $requirement): ?self
    {
        return match (true) {
            $requirement->accepted_at !== null => self::Verified,
            (bool) $requirement->getAttribute('commits_exists') => self::AwaitingAcceptance,
            default => null,
        };
    }

    /**
     * A subtree is only as far delivered as its least delivered decided node.
     *
     * @param  list<self>  $statuses
     */
    public static function combined(array $statuses): self
    {
        if ($statuses === [] || in_array(self::NotBuilt, $statuses, true)) {
            return self::NotBuilt;
        }

        return in_array(self::AwaitingAcceptance, $statuses, true) ? self::AwaitingAcceptance : self::Verified;
    }
}
