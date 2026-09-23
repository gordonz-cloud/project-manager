<?php

namespace App\Support;

final class TraceGraph
{
    /**
     * @var array<string, TraceNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, TraceEdge>
     */
    private array $edges = [];

    public function addNode(TraceNode $node): void
    {
        $this->nodes[$node->key] = $node;
    }

    public function addEdge(TraceEdge $edge): void
    {
        if (! isset($this->nodes[$edge->from], $this->nodes[$edge->to])) {
            return;
        }

        $this->edges["{$edge->from}|{$edge->to}|{$edge->kind}"] = $edge;
    }

    /**
     * @return array<string, TraceNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<TraceEdge>
     */
    public function edges(): array
    {
        return array_values($this->edges);
    }

    public function node(string $key): ?TraceNode
    {
        return $this->nodes[$key] ?? null;
    }

    /**
     * @return list<TraceEdge>
     */
    public function incoming(string $key): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (TraceEdge $edge): bool => $edge->to === $key,
        ));
    }

    /**
     * @return list<TraceEdge>
     */
    public function outgoing(string $key): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (TraceEdge $edge): bool => $edge->from === $key,
        ));
    }

    /**
     * @return array<string, list<TraceNode>>
     */
    public function nodesByLayer(): array
    {
        $groups = [];

        foreach ($this->nodes as $node) {
            $groups[$node->layer][] = $node;
        }

        foreach ($groups as &$nodes) {
            usort($nodes, fn (TraceNode $a, TraceNode $b): int => [$a->type, $a->title] <=> [$b->type, $b->title]);
        }

        return $groups;
    }
}
