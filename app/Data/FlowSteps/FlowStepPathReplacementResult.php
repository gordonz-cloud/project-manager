<?php

namespace App\Data\FlowSteps;

readonly class FlowStepPathReplacementResult
{
    public function __construct(
        public string $path,
        public int $stepsWritten,
    ) {}

    public function summary(): string
    {
        return "{$this->path}: {$this->stepsWritten} steps now";
    }
}
