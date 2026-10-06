<?php

namespace App\Data\Requirements;

final readonly class RequirementTreeSaveResult
{
    public int $created;

    /**
     * @param  array<string, int>  $assigned  ref (or "row N") => number given to each new node
     * @param  list<int>  $voided  numbers of rules voided because a decided node superseded them
     */
    public function __construct(
        public array $assigned,
        public int $updated,
        public array $voided,
    ) {
        $this->created = count($assigned);
    }
}
