<?php

namespace App\Data\Flowcharts;

use App\Models\Flowchart;

/**
 * One flowchart's staleness check result: which nodes' file/function no longer
 * match the repo, and why. The flowchart's `stale_checked_at`/`stale_nodes` are
 * already saved by the time this is returned.
 */
final readonly class FlowchartStaleCheck
{
    /**
     * @param  list<array{id: string, label: string, file: string, function: string, reason: string}>  $staleNodes
     */
    public function __construct(
        public Flowchart $flowchart,
        public int $checkedNodes,
        public array $staleNodes,
    ) {}
}
