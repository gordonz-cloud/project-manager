<?php

namespace App\Data\Workbench;

use App\Enums\DataModelStatus;
use App\Enums\FeatureStatus;
use App\Models\DataModel;
use App\Models\Feature;

/**
 * How much of a use case (or a group of them) is left to build, rolled up from
 * its features and the data models those features use. Void features don't
 * count toward the total; a deprecated or existing model doesn't need building.
 */
final readonly class WorkbenchProgress
{
    /**
     * @param  array<int, DataModel>  $unfinishedModels  keyed by model id, so merging de-duplicates
     */
    public function __construct(
        public int $doneFeatures,
        public int $totalFeatures,
        public array $unfinishedModels = [],
    ) {}

    /**
     * @param  iterable<Feature>  $features
     */
    public static function forFeatures(iterable $features): self
    {
        $done = 0;
        $total = 0;
        $unfinishedModels = [];

        foreach ($features as $feature) {
            if ($feature->status === FeatureStatus::Void) {
                continue;
            }

            $total++;
            $done += $feature->status === FeatureStatus::Done ? 1 : 0;

            foreach ($feature->dataModels as $dataModel) {
                if (in_array($dataModel->status, [DataModelStatus::Designing, DataModelStatus::Planned], true)) {
                    $unfinishedModels[$dataModel->id] = $dataModel;
                }
            }
        }

        return new self($done, $total, $unfinishedModels);
    }

    public function merge(self $other): self
    {
        return new self(
            $this->doneFeatures + $other->doneFeatures,
            $this->totalFeatures + $other->totalFeatures,
            $this->unfinishedModels + $other->unfinishedModels,
        );
    }

    public function isComplete(): bool
    {
        return $this->doneFeatures === $this->totalFeatures && $this->unfinishedModels === [];
    }

    public function tone(): string
    {
        return $this->isComplete() ? 'success' : 'warning';
    }

    public function badge(): string
    {
        $badge = "功能 {$this->doneFeatures}/{$this->totalFeatures}";

        if ($this->unfinishedModels !== []) {
            $badge .= ' · Model '.count($this->unfinishedModels).' 待建';
        }

        return $badge;
    }
}
