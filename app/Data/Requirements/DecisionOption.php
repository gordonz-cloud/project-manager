<?php

namespace App\Data\Requirements;

use App\Enums\DecisionOutcome;
use App\Enums\RequirementDecider;

/**
 * One answer Gordon can pick for a 提议/冲突, with what follows from it. recordAs/recordDate: picking it records
 * someone else's decision, e.g. 老板 already approved it in a document on that date.
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
        public ?RequirementDecider $recordAs = null,
        public ?string $recordDate = null,
    ) {}

    /**
     * @return array{key: string, label: string, outcome: string, consequence: string, result_title?: string, recommended: bool, record_as?: string, record_date?: string}
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
            'record_as' => $this->recordAs?->value,
            'record_date' => $this->recordDate,
        ], fn (mixed $value): bool => $value !== null);
    }
}
