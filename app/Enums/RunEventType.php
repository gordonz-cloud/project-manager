<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RunEventType: string implements HasLabel
{
    case Entered = 'entered';
    case MessageAdded = 'message_added';
    case AttemptStarted = 'attempt_started';
    case AttemptFinished = 'attempt_finished';
    case GapDetected = 'gap_detected';
    case DecisionRecorded = 'decision_recorded';
    case EdgeTaken = 'edge_taken';
    case BackEdgeTaken = 'back_edge_taken';
    case Blocked = 'blocked';
    case Approved = 'approved';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entered => '进入节点',
            self::MessageAdded => '新增消息',
            self::AttemptStarted => '开始执行',
            self::AttemptFinished => '结束执行',
            self::GapDetected => '发现缺口',
            self::DecisionRecorded => '记录决定',
            self::EdgeTaken => '沿边前进',
            self::BackEdgeTaken => '沿回边返回',
            self::Blocked => '阻塞',
            self::Approved => '已批准',
            self::Completed => '完成',
        };
    }
}
