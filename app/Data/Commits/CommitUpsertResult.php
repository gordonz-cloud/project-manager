<?php

namespace App\Data\Commits;

use App\Models\Commit;

readonly class CommitUpsertResult
{
    public function __construct(
        public Commit $commit,
        public bool $created,
        public bool $autoAssigned,
    ) {}
}
