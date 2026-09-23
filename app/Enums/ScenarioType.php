<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScenarioType: string implements HasLabel
{
    case Happy = 'happy';
    case Alternate = 'alternate';
    case Error = 'error';
    case Concurrency = 'concurrency';
    case Recovery = 'recovery';
    case Abuse = 'abuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Happy => '正常路径',
            self::Alternate => '替代路径',
            self::Error => '错误路径',
            self::Concurrency => '并发路径',
            self::Recovery => '恢复路径',
            self::Abuse => '滥用路径',
        };
    }
}
