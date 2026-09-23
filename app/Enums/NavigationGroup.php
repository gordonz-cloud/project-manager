<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Scope;
    case Requirements;
    case Behavior;
    case Solution;
    case Delivery;
    case Execution;
    case Evidence;

    public function getLabel(): string
    {
        return match ($this) {
            self::Scope => 'Scope',
            self::Requirements => 'Requirements',
            self::Behavior => 'Behavior',
            self::Solution => 'Solution',
            self::Delivery => 'Delivery',
            self::Execution => 'Execution',
            self::Evidence => 'Evidence',
        };
    }
}
