<?php

namespace App\Data\Tests;

use Closure;

/**
 * One business area (tests.module) of the Test Matrix: its local roots, each under the
 * breadcrumb of prerequisite steps that live in other areas.
 */
final readonly class TestArea
{
    /**
     * @param  list<array{breadcrumb: list<string>, nodes: list<TestTreeNode>}>  $branches  consecutive roots sharing a breadcrumb share a branch
     */
    public function __construct(
        public string $name,
        public array $branches,
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
        $rootsByArea = [];
        self::collectLocalRoots($tree, null, [], $rootsByArea);

        $areas = [];
        foreach ($rootsByArea as $name => $roots) {
            $areas[] = self::fromRoots((string) $name, $roots);
        }

        usort($areas, fn (self $a, self $b): int => [$a->rollup->failed === 0, $a->name] <=> [$b->rollup->failed === 0, $b->name]);

        return $areas;
    }

    /**
     * @param  Closure(TestTreeNode): bool  $matches
     */
    public function matching(Closure $matches): ?self
    {
        $branches = [];

        foreach ($this->branches as $branch) {
            $nodes = TestTreeNode::matchingAll($branch['nodes'], $matches);

            if ($nodes !== []) {
                $branches[] = ['breadcrumb' => $branch['breadcrumb'], 'nodes' => $nodes];
            }
        }

        return $branches === [] ? null : new self($this->name, $branches, $this->rollup);
    }

    /**
     * @param  list<TestTreeNode>  $nodes
     * @param  list<string>  $ancestors  titles from the tree root down to the parent
     * @param  array<string, list<array{breadcrumb: list<string>, node: TestTreeNode}>>  $rootsByArea
     */
    private static function collectLocalRoots(array $nodes, ?string $parentArea, array $ancestors, array &$rootsByArea): void
    {
        foreach ($nodes as $node) {
            $area = $node->test->area();

            if ($area !== $parentArea) {
                $rootsByArea[$area][] = ['breadcrumb' => $ancestors, 'node' => $node->withinArea()];
            }

            self::collectLocalRoots($node->children, $area, [...$ancestors, $node->test->title], $rootsByArea);
        }
    }

    /**
     * @param  list<array{breadcrumb: list<string>, node: TestTreeNode}>  $roots
     */
    private static function fromRoots(string $name, array $roots): self
    {
        $branches = [];
        $rollup = new TestRollup;

        foreach ($roots as $root) {
            $rollup = $rollup->plus($root['node']->rollup);
            $last = array_key_last($branches);

            if ($last !== null && $branches[$last]['breadcrumb'] === $root['breadcrumb']) {
                $branches[$last]['nodes'][] = $root['node'];
            } else {
                $branches[] = ['breadcrumb' => $root['breadcrumb'], 'nodes' => [$root['node']]];
            }
        }

        return new self($name, $branches, $rollup);
    }
}
