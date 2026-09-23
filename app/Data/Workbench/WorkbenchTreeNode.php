<?php

namespace App\Data\Workbench;

/**
 * One row of the workbench tree. `key` is "{type}:{id}" for a record row and
 * "{parentKey}#{name}" for a folder row, which only groups children.
 */
final readonly class WorkbenchTreeNode
{
    /**
     * @param  list<self>  $children
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public ?string $tone = null,
        public ?string $badge = null,
        public array $children = [],
        public bool $isFolder = false,
        public bool $isFailureBranch = false,
        public bool $hasNoScenario = false,
    ) {}

    /**
     * @param  list<self>  $children
     */
    public static function folder(string $parentKey, string $name, string $label, string $icon, array $children): ?self
    {
        if ($children === []) {
            return null;
        }

        return new self(
            key: "{$parentKey}#{$name}",
            label: $label,
            icon: $icon,
            badge: (string) count($children),
            children: $children,
            isFolder: true,
        );
    }

    /**
     * This row pruned to the branches whose label contains the needle; null when nothing matches.
     */
    public function matching(string $needle): ?self
    {
        if (str_contains(mb_strtolower($this->label), $needle)) {
            return $this;
        }

        $children = self::matchingAll($this->children, $needle);

        if ($children === []) {
            return null;
        }

        return new self($this->key, $this->label, $this->icon, $this->tone, $this->badge, $children, $this->isFolder, $this->isFailureBranch, $this->hasNoScenario);
    }

    /**
     * @param  list<self>  $nodes
     * @return list<self>
     */
    public static function matchingAll(array $nodes, string $needle): array
    {
        return array_values(array_filter(array_map(
            fn (self $node): ?self => $node->matching($needle),
            $nodes,
        )));
    }

    /**
     * Keys from a root down to the row with this key, or null when it is not in the tree.
     *
     * @param  list<self>  $nodes
     * @return list<string>|null
     */
    public static function pathTo(array $nodes, string $key): ?array
    {
        foreach ($nodes as $node) {
            if ($node->key === $key) {
                return [$node->key];
            }

            $path = self::pathTo($node->children, $key);

            if ($path !== null) {
                return [$node->key, ...$path];
            }
        }

        return null;
    }
}
