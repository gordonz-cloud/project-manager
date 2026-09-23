<?php

namespace App\Services\FlowSteps;

use App\Data\FlowSteps\FlowStepShapeApplicationResult;
use App\Models\FlowStep;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ApplyFlowStepShapes
{
    public function handle(string $file): FlowStepShapeApplicationResult
    {
        if (! File::exists($file)) {
            throw new InvalidArgumentException("No such file: {$file}");
        }

        $rows = json_decode(File::get($file), true);

        if (! is_array($rows)) {
            throw new InvalidArgumentException("Not a JSON list: {$file}");
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

        return new FlowStepShapeApplicationResult(
            written: $written,
            missingIds: $missing,
        );
    }
}
