<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RequestReplyEdgeKind: string implements HasLabel
{
    case Next = 'next';
    case OnFailure = 'on_failure';
    case Optional = 'optional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Next => '下一步',
            self::OnFailure => '失败后',
            self::Optional => '可选',
        };
    }
}
