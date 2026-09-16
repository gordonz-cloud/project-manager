<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RequirementStatus: string implements HasLabel
{
    case Pending = '待定';
    case Confirmed = '已确认';
    case InProgress = '进行中';
    case Done = '完成';
    case OnHold = '暂缓';
    case Void = '作废';

    public function getLabel(): string
    {
        return $this->value;
    }
}
