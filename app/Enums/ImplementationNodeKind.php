<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImplementationNodeKind: string implements HasLabel
{
    case Discovery = 'discovery';
    case Decision = 'decision';
    case Design = 'design';
    case Migration = 'migration';
    case Code = 'code';
    case Test = 'test';
    case Review = 'review';
    case Release = 'release';

    public function getLabel(): string
    {
        return match ($this) {
            self::Discovery => '发现',
            self::Decision => '决策',
            self::Design => '设计',
            self::Migration => '迁移',
            self::Code => '编码',
            self::Test => '测试',
            self::Review => '审查',
            self::Release => '发布',
        };
    }
}
