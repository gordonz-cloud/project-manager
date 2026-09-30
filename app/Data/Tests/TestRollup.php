<?php

namespace App\Data\Tests;

/**
 * How a subtree's nodes stand, each node counted once under its most telling state.
 */
final readonly class TestRollup
{
    public function __construct(
        public int $passed = 0,
        public int $failed = 0,
        public int $blocked = 0,
        public int $manual = 0,
        public int $fakeGreen = 0,
    ) {}

    public static function of(TestNodeState $state): self
    {
        return new self(
            passed: (int) ($state === TestNodeState::Passed),
            failed: (int) ($state === TestNodeState::Failed),
            blocked: (int) ($state === TestNodeState::Blocked),
            manual: (int) ($state === TestNodeState::Manual),
            fakeGreen: (int) ($state === TestNodeState::FakeGreen),
        );
    }

    public function plus(self $other): self
    {
        return new self(
            $this->passed + $other->passed,
            $this->failed + $other->failed,
            $this->blocked + $other->blocked,
            $this->manual + $other->manual,
            $this->fakeGreen + $other->fakeGreen,
        );
    }
}
