<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScenarioPriority: string implements HasLabel
{
    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critical => '关键',
            self::High => '高',
            self::Normal => '普通',
            self::Low => '低',
        };
    }
}
