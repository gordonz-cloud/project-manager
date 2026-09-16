<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequirementStatus: string implements HasColor, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Uncertain => 'gray',
            self::Todo => 'info',
            self::InProgress => 'warning',
            self::Done => 'success',
            self::OnHold => 'gray',
            self::Void => 'danger',
        };
    }
}
