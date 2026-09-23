<?php

namespace App\Data\Workbench;

final readonly class WorkbenchGraphState
{
    /**
     * @param  array<string, bool>  $expandedTreeSections
     * @param  array<int, bool>  $expandedTreeModules
     * @param  array<int, bool>  $expandedTreeModulesAll
     * @param  array<string, bool>  $expandedTreeGroups
     * @param  array<string, bool>  $expandedTreeGroupsAll
     */
    public function __construct(
        public string $scopeType,
        public ?int $moduleSpecId,
        public ?int $moduleId,
        public ?string $selectedKey,
        public string $search,
        public string $layerFilter,
        public bool $blockingOnly,
        public bool $gapsOnly,
        public bool $focusMode,
        public int $focusDepth,
        public array $expandedTreeSections,
        public array $expandedTreeModules,
        public array $expandedTreeModulesAll,
        public array $expandedTreeGroups,
        public array $expandedTreeGroupsAll,
    ) {}
}
