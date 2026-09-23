<?php

namespace App\Console\Commands;

use App\Services\FlowSteps\ReplaceFeatureFlowStepPath;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Replaces every hop of one path under one feature with the list in a JSON
 * file: {"project": "sg", "feature": 14, "path": "拒付回调-主路径",
 * "steps": [{"order": 1, "step": "...", "file": "...", "function": "...",
 * "input": "...", "change": "...", "output": "..."}]}. A path is re-cut as a
 * whole — rows merge and split when the granularity changes — so the unit of
 * replacement is the path, not the row. Several readers run at once; they
 * each own different features.
 */
class ReplaceFlowStepPath extends Command
{
    protected $signature = 'flow-steps:replace-path {file : JSON describing one path}';

    protected $description = 'Replace all hops of one feature path from a JSON file';

    public function handle(ReplaceFeatureFlowStepPath $replaceFeatureFlowStepPath): int
    {
        $file = (string) $this->argument('file');

        try {
            $result = $replaceFeatureFlowStepPath->handle($file);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        return self::SUCCESS;
    }
}
