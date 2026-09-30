<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TestPriority: string implements HasColor, HasLabel
{
    case P0 = 'P0';
    case P1 = 'P1';
    case P2 = 'P2';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::P0 => 'danger',
            self::P1 => 'warning',
            self::P2 => 'gray',
        };
    }
}
