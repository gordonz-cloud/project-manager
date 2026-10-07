<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who must decide a 提议 or 冲突: product direction and promises to users go to 老板, how to build it to Gordon.
 */
enum RequirementDecider: string implements HasLabel
{
    case Gordon = 'Gordon';
    case Boss = '老板';

    public function getLabel(): string
    {
        return $this->value;
    }
}
