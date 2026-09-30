<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TestLastResult: string implements HasColor, HasLabel
{
    case NotRun = '未跑';
    case Passed = '通过';
    case Failed = '失败';
    case Skipped = '跳过';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotRun => 'gray',
            self::Passed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'info',
        };
    }
}
