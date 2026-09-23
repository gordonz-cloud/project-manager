<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeatureTrigger: string implements HasLabel
{
    case Http = 'HTTP';
    case Ui = 'UI';
    case Scheduler = 'Scheduler';
    case Webhook = 'Webhook';
    case Queue = 'Queue';
    case Cli = 'CLI';
    case Event = 'Event';

    public function getLabel(): string
    {
        return $this->value;
    }

    public static function labelFor(string $state): string
    {
        return self::tryFrom($state)?->getLabel() ?? $state;
    }
}
