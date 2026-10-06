<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Level in the requirement tree: 目标 at the root, 子目标 under a 目标, 规则 (one verifiable statement) under either.
 */
enum RequirementKind: string implements HasLabel
{
    case Goal = '目标';
    case SubGoal = '子目标';
    case Rule = '规则';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * @return list<self> parent kinds this kind may sit under; empty means it must be a root
     */
    public function allowedParents(): array
    {
        return match ($this) {
            self::Goal => [],
            self::SubGoal => [self::Goal],
            self::Rule => [self::Goal, self::SubGoal],
        };
    }
}
