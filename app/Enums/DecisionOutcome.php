<?php

namespace App\Enums;

/**
 * What picking a decision option does to the 提议/冲突: accept/custom make it 已定 (custom under its own wording),
 * reject drops the proposal, keep_current drops the change and keeps the rule it would have replaced.
 */
enum DecisionOutcome: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case KeepCurrent = 'keep_current';
    case Custom = 'custom';

    public function resultingStatus(): RequirementStatus
    {
        return match ($this) {
            self::Accept, self::Custom => RequirementStatus::Decided,
            self::Reject, self::KeepCurrent => RequirementStatus::Void,
        };
    }
}
