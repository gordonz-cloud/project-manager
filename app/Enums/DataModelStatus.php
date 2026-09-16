<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DataModelStatus: string implements HasColor, HasLabel
{
    case Existing = '现有';
    case Designing = '设计中';
    case Planned = '计划中';
    case Deprecated = '废弃';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Existing => 'success',
            self::Designing => 'warning',
            self::Planned => 'info',
            self::Deprecated => 'danger',
        };
    }
}
