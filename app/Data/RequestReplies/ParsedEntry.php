<?php

namespace App\Data\RequestReplies;

use App\Enums\FeatureTrigger;

final readonly class ParsedEntry
{
    public function __construct(
        public FeatureTrigger $trigger,
        public ?string $method,
        public string $entry,
    ) {}

    public function title(): string
    {
        return trim("{$this->method} {$this->entry}");
    }
}
