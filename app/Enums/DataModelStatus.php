<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DataModelStatus: string implements HasLabel
{
    case Existing = '现有';
    case Designing = '设计中';
    case Planned = '计划中';
    case Deprecated = '废弃';

    public function getLabel(): string
    {
        return $this->value;
    }
}
