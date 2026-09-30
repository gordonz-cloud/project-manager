<?php

namespace App\Data\Tests;

/**
 * How a subtree's nodes stand, each node counted once under its most telling state; gaps are counted on the side.
 */
final readonly class TestRollup
{
    public function __construct(
        public int $passed = 0,
        public int $failed = 0,
        public int $blocked = 0,
        public int $gaps = 0,
        public int $fakeGreen = 0,
    ) {}

    public static function of(TestNodeState $state, bool $isGap): self
    {
        return new self(
            passed: (int) ($state === TestNodeState::Passed),
            failed: (int) ($state === TestNodeState::Failed),
            blocked: (int) ($state === TestNodeState::Blocked),
            gaps: (int) $isGap,
            fakeGreen: (int) ($state === TestNodeState::FakeGreen),
        );
    }

    public function plus(self $other): self
    {
        return new self(
            $this->passed + $other->passed,
            $this->failed + $other->failed,
            $this->blocked + $other->blocked,
            $this->gaps + $other->gaps,
            $this->fakeGreen + $other->fakeGreen,
        );
    }
}
