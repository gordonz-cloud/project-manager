<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether a node is automated: Wrong means the automated test is green but does not prove the expected result.
 */
enum TestAuto: string implements HasColor, HasLabel
{
    case Yes = 'yes';
    case No = 'no';
    case Partial = 'partial';
    case Wrong = 'WRONG';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'gray',
            self::Partial => 'warning',
            self::Wrong => 'danger',
        };
    }
}
