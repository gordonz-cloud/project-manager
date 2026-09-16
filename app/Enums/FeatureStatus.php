<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeatureStatus: string implements HasColor, HasLabel
{
    case Uncertain = '不确定';
    case Todo = '待做';
    case InDevelopment = '开发中';
    case InVerification = '验证中';
    case Done = '完成';
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
            self::InDevelopment => 'warning',
            self::InVerification => 'primary',
            self::Done => 'success',
            self::Void => 'danger',
        };
    }
}
