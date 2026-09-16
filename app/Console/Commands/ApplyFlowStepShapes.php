<?php

namespace App\Console\Commands;

use App\Models\FlowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! File::exists($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->error("Not a JSON list: {$path}");

            return self::FAILURE;
        }

        $written = 0;
        $missing = [];

        foreach ($rows as $row) {
            $step = FlowStep::query()->find((int) ($row['id'] ?? 0));

            if ($step === null) {
                $missing[] = $row['id'] ?? '?';

                continue;
            }

            $step->forceFill([
                'input' => array_key_exists('input', $row) ? $row['input'] : $step->input,
                'output' => array_key_exists('output', $row) ? $row['output'] : $step->output,
            ])->save();
            $written++;
        }

        $this->info("{$written} steps written".($missing === [] ? '' : '; no step with id '.implode(', ', $missing)));

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }
}
