<?php

namespace App\Data\Tests;

use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Models\Test;
use Closure;

/**
 * One Test Matrix node with its subtree; rollup counts the node itself plus every descendant.
 */
final readonly class TestTreeNode
{
    /**
     * @param  list<self>  $children
     */
    public function __construct(
        public Test $test,
        public TestNodeState $state,
        public array $children,
        public TestRollup $rollup,
    ) {}

    /**
     * @param  list<self>  $children
     */
    public static function build(Test $test, bool $hasFailedAncestor, array $children): self
    {
        $state = self::stateOf($test, $hasFailedAncestor);
        $rollup = array_reduce($children, fn (TestRollup $sum, self $child): TestRollup => $sum->plus($child->rollup), TestRollup::of($state, $test->isGap()));

        return new self($test, $state, $children, $rollup);
    }

    /**
     * The same node with only the descendants that stay in its own business area; state is kept.
     */
    public function withinArea(): self
    {
        $children = array_values(array_map(
            fn (self $child): self => $child->withinArea(),
            array_filter($this->children, fn (self $child): bool => $child->test->area() === $this->test->area()),
        ));
        $rollup = array_reduce($children, fn (TestRollup $sum, self $child): TestRollup => $sum->plus($child->rollup), TestRollup::of($this->state, $this->test->isGap()));

        return new self($this->test, $this->state, $children, $rollup);
    }

    public static function stateOf(Test $test, bool $hasFailedAncestor): TestNodeState
    {
        return match (true) {
            $test->auto === TestAuto::Wrong => TestNodeState::FakeGreen,
            $hasFailedAncestor => TestNodeState::Blocked,
            $test->last_result === TestLastResult::Failed => TestNodeState::Failed,
            $test->auto === TestAuto::No => TestNodeState::Manual,
            $test->last_result === TestLastResult::Passed => TestNodeState::Passed,
            default => TestNodeState::NotRun,
        };
    }

    /**
     * Nodes matching the predicate, kept with their ancestor path; everything else dropped.
     *
     * @param  list<self>  $nodes
     * @param  Closure(self): bool  $matches
     * @return list<self>
     */
    public static function matchingAll(array $nodes, Closure $matches): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            $children = self::matchingAll($node->children, $matches);

            if ($children !== [] || $matches($node)) {
                $kept[] = new self($node->test, $node->state, $children, $node->rollup);
            }
        }

        return $kept;
    }

    /**
     * @param  list<self>  $nodes
     */
    public static function total(array $nodes): TestRollup
    {
        return array_reduce($nodes, fn (TestRollup $sum, self $node): TestRollup => $sum->plus($node->rollup), new TestRollup);
    }

    /**
     * @param  list<self>  $nodes
     */
    public static function count(array $nodes): int
    {
        return array_sum(array_map(fn (self $node): int => 1 + self::count($node->children), $nodes));
    }
}
