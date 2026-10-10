<?php

namespace App\Data\Tests;

final readonly class TestTreeSaveResult
{
    public int $created;

    /**
     * @param  array<string, int>  $assigned  ref (or "row N") => number given to each new node
     */
    public function __construct(
        public array $assigned,
        public int $updated,
    ) {
        $this->created = count($assigned);
    }
}
