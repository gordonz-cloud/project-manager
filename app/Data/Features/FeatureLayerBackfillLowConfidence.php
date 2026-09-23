<?php

namespace App\Data\Features;

readonly class FeatureLayerBackfillLowConfidence
{
    /**
     * @param  list<int>  $candidateNumbers
     */
    public function __construct(
        public string $title,
        public array $candidateNumbers,
        public string $reason,
    ) {}
}
