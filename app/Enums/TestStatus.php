<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TestStatus: string implements HasLabel
{
    case ToWrite = '待写';
    case Valid = '有效';
    case Stale = '过时';
    case Disabled = '停用';

    public function getLabel(): string
    {
        return $this->value;
    }
}
