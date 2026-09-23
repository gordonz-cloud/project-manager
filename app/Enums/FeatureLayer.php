<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeatureLayer: string implements HasLabel
{
    case Backend = '后端';
    case Frontend = '前端';
    case Admin = '后台';
    case Config = '配置';
    case Manual = '人工';

    public function getLabel(): string
    {
        return $this->value;
    }

    public static function labelFor(string $state): string
    {
        return self::tryFrom($state)?->getLabel() ?? $state;
    }
}
