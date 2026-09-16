<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TestLastResult: string implements HasLabel
{
    case NotRun = '未跑';
    case Passed = '通过';
    case Failed = '失败';
    case Blocked = '阻塞';
    case Skipped = '跳过';

    public function getLabel(): string
    {
        return $this->value;
    }
}
