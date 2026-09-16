<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RequirementStatus: string implements HasLabel
{
    case Uncertain = '不确定';
    case Todo = '待做';
    case InProgress = '进行中';
    case Done = '完成';
    case OnHold = '暂缓';
    case Void = '作废';

    public function getLabel(): string
    {
        return $this->value;
    }
}
