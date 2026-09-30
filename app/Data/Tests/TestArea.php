<?php

namespace App\Data\Tests;

use Closure;

/**
 * One business area (tests.module) of the Test Matrix: the smallest subtree holding the area's nodes,
 * with ancestors from other areas kept as muted nodes so the tree stays real.
 */
final readonly class TestArea
{
    /**
     * @param  list<TestTreeNode>  $nodes
     */
    public function __construct(
        public string $name,
        public array $nodes,
        public TestRollup $rollup,
    ) {}

    /**
     * Areas with failures first, then by name.
     *
     * @param  list<TestTreeNode>  $tree
     * @return list<self>
     */
    public static function groupAll(array $tree): array
    {
        $areas = [];

        foreach (array_unique(self::areaNames($tree)) as $name) {
            $nodes = TestTreeNode::withinAreaAll($tree, $name);
            $areas[] = new self($name, $nodes, TestTreeNode::total($nodes));
        }

        usort($areas, fn (self $a, self $b): int => [$a->rollup->failed === 0, $a->name] <=> [$b->rollup->failed === 0, $b->name]);

        return $areas;
    }

    /**
     * @param  Closure(TestTreeNode): bool  $matches  asked only of the area's own nodes
     */
    public function matching(Closure $matches): ?self
    {
        $nodes = TestTreeNode::matchingAll($this->nodes, fn (TestTreeNode $node): bool => ! $node->muted && $matches($node));

        return $nodes === [] ? null : new self($this->name, $nodes, $this->rollup);
    }

    /**
     * @param  list<TestTreeNode>  $nodes
     * @return list<string>
     */
    private static function areaNames(array $nodes): array
    {
        return array_merge(...array_map(fn (TestTreeNode $node): array => [$node->test->area(), ...self::areaNames($node->children)], $nodes));
    }
}
