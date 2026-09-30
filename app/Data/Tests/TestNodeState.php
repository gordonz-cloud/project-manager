<?php

namespace App\Data\Tests;

/**
 * What one node's dot says. Blocked is never stored: it means some ancestor failed.
 */
enum TestNodeState
{
    case FakeGreen;
    case Blocked;
    case Failed;
    case Manual;
    case Passed;
    case NotRun;

    public function symbol(): string
    {
        return match ($this) {
            self::FakeGreen => '⚠',
            self::Blocked => '⛔',
            self::Failed => '✗',
            self::Passed => '●',
            self::Manual, self::NotRun => '○',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FakeGreen => '假绿',
            self::Blocked => '被挡',
            self::Failed => '失败',
            self::Manual => '手测',
            self::Passed => '通过',
            self::NotRun => '未跑',
        };
    }
}
