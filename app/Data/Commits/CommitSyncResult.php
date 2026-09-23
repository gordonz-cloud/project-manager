<?php

namespace App\Data\Commits;

readonly class CommitSyncResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $autoAssigned,
        public int $unassigned,
    ) {}

    public function summary(): string
    {
        return "新增 {$this->created}、更新 {$this->updated}、自动挂上 {$this->autoAssigned}、未挂 {$this->unassigned}";
    }
}
