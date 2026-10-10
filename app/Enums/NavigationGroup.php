<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Scope;
    case Requirements;
    case Solution;
    case Evidence;

    public function getLabel(): string
    {
        return match ($this) {
            self::Scope => 'Scope',
            self::Requirements => 'Requirements',
            self::Solution => 'Solution',
            self::Evidence => 'Evidence',
        };
    }
}
