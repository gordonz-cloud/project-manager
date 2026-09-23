<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImplementationNodeEdgeKind: string implements HasLabel
{
    case Forward = 'forward';
    case Back = 'back';
    case Parallel = 'parallel';
    case Join = 'join';
    case Conditional = 'conditional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Forward => '前向',
            self::Back => '回边',
            self::Parallel => '并行',
            self::Join => '汇合',
            self::Conditional => '条件',
        };
    }
}
