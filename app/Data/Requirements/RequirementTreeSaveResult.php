<?php

namespace App\Data\Requirements;

final readonly class RequirementTreeSaveResult
{
    public int $created;

    /**
     * @param  array<string, int>  $assigned  ref (or "row N") => number given to each new node
     * @param  list<int>  $voided  numbers of rules voided because a decided node superseded them
     * @param  list<string>  $warnings  saved anyway, but worth fixing (e.g. a title too long to read at a glance)
     */
    public function __construct(
        public array $assigned,
        public int $updated,
        public array $voided,
        public array $warnings = [],
    ) {
        $this->created = count($assigned);
    }
}
