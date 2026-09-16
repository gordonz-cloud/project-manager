<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeatureStatus: string implements HasLabel
{
    case Idea = '想法';
    case Todo = '待做';
    case InDevelopment = '开发中';
    case InVerification = '验证中';
    case Done = '完成';
    case Void = '作废';

    public function getLabel(): string
    {
        return $this->value;
    }
}
