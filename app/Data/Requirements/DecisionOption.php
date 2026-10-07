<?php

namespace App\Data\Requirements;

use App\Enums\DecisionOutcome;

/**
 * One answer Gordon can pick for a 提议/冲突, with what follows from it.
 */
final readonly class DecisionOption
{
    public function __construct(
        public string $key,
        public string $label,
        public DecisionOutcome $outcome,
        public string $consequence,
        public ?string $resultTitle = null,
        public bool $recommended = false,
    ) {}

    /**
     * @return array{key: string, label: string, outcome: string, consequence: string, result_title?: string, recommended: bool}
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'outcome' => $this->outcome->value,
            'consequence' => $this->consequence,
            'result_title' => $this->resultTitle,
            'recommended' => $this->recommended,
        ], fn (mixed $value): bool => $value !== null);
    }
}
