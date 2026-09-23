<?php

namespace App\Console\Commands;

use App\Services\FlowSteps\ApplyFlowStepShapes as ApplyFlowStepShapesService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Writes data shapes onto flow steps from a JSON file of
 * [{"id": 12, "input": "...", "output": "..."}]. The shapes are what a
 * Log::debug at that hop would print; they come from reading the code, and
 * several readers work in parallel, so the writes go through one place that
 * quotes for them and touches only the two columns.
 */
class ApplyFlowStepShapes extends Command
{
    protected $signature = 'flow-steps:apply-shapes {file : JSON list of {id, input, output}}';

    protected $description = 'Write input/output data shapes onto flow steps from a JSON file';

    public function handle(ApplyFlowStepShapesService $applyFlowStepShapes): int
    {
        $file = (string) $this->argument('file');

        try {
            $result = $applyFlowStepShapes->handle($file);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        return $result->hasMissingSteps() ? self::FAILURE : self::SUCCESS;
    }
}
