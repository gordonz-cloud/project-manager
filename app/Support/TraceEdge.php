<?php

namespace App\Support;

final readonly class TraceEdge
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $kind,
        public string $label,
        public array $meta = [],
    ) {}
}
