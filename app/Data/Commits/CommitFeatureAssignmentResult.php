<?php

namespace App\Data\Commits;

readonly class CommitFeatureAssignmentResult
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(
        public int $assigned,
        public int $alreadyAssigned,
        public array $problems,
    ) {}

    public function summary(): string
    {
        return "assigned {$this->assigned}, already assigned {$this->alreadyAssigned}, problems ".count($this->problems);
    }

    public function hasProblems(): bool
    {
        return $this->problems !== [];
    }
}
