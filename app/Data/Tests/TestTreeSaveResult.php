<?php

namespace App\Data\Tests;

final readonly class TestTreeSaveResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $nodesWithFeaturesSynced,
    ) {}
}
