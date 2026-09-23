<?php

namespace App\Data\Commits;

use Carbon\CarbonImmutable;

readonly class GitCommitData
{
    public function __construct(
        public string $hash,
        public string $author,
        public string $subject,
        public ?string $body,
        public CarbonImmutable $committedAt,
    ) {}
}
