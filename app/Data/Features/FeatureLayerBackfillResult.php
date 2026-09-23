<?php

namespace App\Data\Features;

readonly class FeatureLayerBackfillResult
{
    /**
     * @param  list<FeatureLayerBackfillLowConfidence>  $lowConfidence
     * @param  list<string>  $unmatchedTitles
     */
    public function __construct(
        public int $highApplied,
        public array $lowConfidence,
        public array $unmatchedTitles,
    ) {}

    public function summary(): string
    {
        return "high 应用 {$this->highApplied}、low ".count($this->lowConfidence).'、未匹配 '.count($this->unmatchedTitles);
    }
}
