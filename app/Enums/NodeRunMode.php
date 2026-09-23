<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NodeRunMode: string implements HasLabel
{
    case Oneshot = 'oneshot';
    case Sticky = 'sticky';
    case Approval = 'approval';

    public function getLabel(): string
    {
        return match ($this) {
            self::Oneshot => '一次性',
            self::Sticky => '常驻对话',
            self::Approval => '等待批准',
        };
    }
}
