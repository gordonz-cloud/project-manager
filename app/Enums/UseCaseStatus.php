<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UseCaseStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Implemented = 'implemented';
    case Verified = 'verified';
    case Void = 'void';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Ready => '就绪',
            self::Implemented => '已实现',
            self::Verified => '已验证',
            self::Void => '作废',
        };
    }
}
