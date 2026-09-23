<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImplementationNodeEdgeKind: string implements HasLabel
{
    case Calls = 'calls';
    case OnSuccess = 'on_success';
    case OnFailure = 'on_failure';
    case Forward = 'forward';
    case Back = 'back';
    case Parallel = 'parallel';
    case Join = 'join';
    case Conditional = 'conditional';

    /**
     * Edge kinds that make up an entry's call tree.
     *
     * @return list<self>
     */
    public static function callTree(): array
    {
        return [self::Calls, self::OnSuccess, self::OnFailure];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Calls => '调用',
            self::OnSuccess => '成功分支',
            self::OnFailure => '失败分支',
            self::Forward => '前向',
            self::Back => '回边',
            self::Parallel => '并行',
            self::Join => '汇合',
            self::Conditional => '条件',
        };
    }
}
