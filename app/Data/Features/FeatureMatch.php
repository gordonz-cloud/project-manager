<?php

namespace App\Data\Features;

use App\Models\Feature;

/**
 * How likely a change belongs to a feature, and why.
 */
final readonly class FeatureMatch
{
    /**
     * @param  list<string>  $matchedFiles
     * @param  list<string>  $matchedWords
     */
    public function __construct(
        public Feature $feature,
        public int $score,
        public array $matchedFiles,
        public bool $entryMatched,
        public array $matchedWords,
    ) {}

    public function why(): string
    {
        $parts = [];

        if ($this->matchedFiles !== []) {
            $parts[] = 'files: '.implode(', ', $this->matchedFiles);
        }

        if ($this->entryMatched) {
            $parts[] = 'entry';
        }

        if ($this->matchedWords !== []) {
            $parts[] = 'words: '.implode(', ', $this->matchedWords);
        }

        return implode(' | ', $parts);
    }
}
