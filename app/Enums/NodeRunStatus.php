<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NodeRunStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Talking = 'talking';
    case Running = 'running';
    case Blocked = 'blocked';
    case Done = 'done';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => '待执行',
            self::Talking => '对话中',
            self::Running => '执行中',
            self::Blocked => '阻塞',
            self::Done => '完成',
            self::Failed => '失败',
        };
    }
}
