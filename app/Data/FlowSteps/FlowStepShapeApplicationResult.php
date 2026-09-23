<?php

namespace App\Data\FlowSteps;

readonly class FlowStepShapeApplicationResult
{
    /**
     * @param  list<int|string>  $missingIds
     */
    public function __construct(
        public int $written,
        public array $missingIds,
    ) {}

    public function summary(): string
    {
        return "{$this->written} steps written".($this->missingIds === [] ? '' : '; no step with id '.implode(', ', $this->missingIds));
    }

    public function hasMissingSteps(): bool
    {
        return $this->missingIds !== [];
    }
}
