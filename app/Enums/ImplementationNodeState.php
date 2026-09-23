<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImplementationNodeState: string implements HasLabel
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Stale = 'stale';
    case Obsolete = 'obsolete';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => '提议中',
            self::Accepted => '已接受',
            self::Stale => '已过时',
            self::Obsolete => '已废弃',
        };
    }
}
