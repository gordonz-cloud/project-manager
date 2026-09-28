<?php

namespace App\Data\Features;

/**
 * A pending change (changed files, hit entry, free-text description) to rank a
 * project's features against.
 */
final readonly class ChangeToMatch
{
    /**
     * @param  list<string>  $files
     */
    public function __construct(
        public array $files = [],
        public ?string $entry = null,
        public ?string $text = null,
    ) {}
}
