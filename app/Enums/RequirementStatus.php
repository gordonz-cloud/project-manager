<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where the decision on a statement stands. Whether it is built is derived, never stored (see DeliveryStatus).
 * 冲突 = a proposal that would replace an existing 已定 rule (supersedes_id points at it).
 */
enum RequirementStatus: string implements HasColor, HasLabel
{
    case Proposed = '提议';
    case Decided = '已定';
    case Conflict = '冲突';
    case Void = '作废';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Proposed => 'info',
            self::Decided => 'success',
            self::Conflict => 'warning',
            self::Void => 'gray',
        };
    }

    public function awaitsDecision(): bool
    {
        return $this === self::Proposed || $this === self::Conflict;
    }
}
