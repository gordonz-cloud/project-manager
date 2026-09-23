<?php

namespace App\Support;

final readonly class TraceNode
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, TraceNode>  $children
     */
    public function __construct(
        public string $key,
        public string $layer,
        public string $type,
        public int $id,
        public string $title,
        public string $status,
        public ?string $subtitle = null,
        public ?string $parentKey = null,
        public bool $blocking = false,
        public int $evidenceCount = 0,
        public array $meta = [],
        public array $children = [],
    ) {}
}
