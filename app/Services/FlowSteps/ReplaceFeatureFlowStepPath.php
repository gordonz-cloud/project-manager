<?php

namespace App\Services\FlowSteps;

use App\Data\FlowSteps\FlowStepPathReplacementResult;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ReplaceFeatureFlowStepPath
{
    public function handle(string $file): FlowStepPathReplacementResult
    {
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['feature'], $spec['path']) || ! is_array($spec['steps'] ?? null)) {
            throw new InvalidArgumentException("Expected {project, feature, path, steps[]} in {$file}");
        }

        $project = Project::query()->where('slug', $spec['project'])->first();
        $feature = $project === null ? null : Feature::query()
            ->where('project_id', $project->id)
            ->where('number', (int) $spec['feature'])
            ->first();

        if ($feature === null) {
            throw new InvalidArgumentException("No feature {$spec['feature']} in project {$spec['project']}");
        }

        foreach ($spec['steps'] as $index => $step) {
            foreach (['order', 'step', 'file', 'output'] as $required) {
                if (! isset($step[$required]) || $step[$required] === '') {
                    throw new InvalidArgumentException("steps[{$index}] is missing {$required}");
                }
            }
        }

        $written = DB::transaction(function () use ($feature, $spec): int {
            FlowStep::query()
                ->where('feature_id', $feature->id)
                ->where('path', $spec['path'])
                ->delete();

            foreach ($spec['steps'] as $step) {
                // project_id is intentionally not fillable; the feature owns it.
                $row = new FlowStep([
                    'feature_id' => $feature->id,
                    'path' => $spec['path'],
                    'order' => (int) $step['order'],
                    'step' => $step['step'],
                    'file' => $step['file'],
                    'function' => $step['function'] ?? null,
                    'input' => $step['input'] ?? null,
                    'change' => $step['change'] ?? null,
                    'output' => $step['output'],
                ]);
                $row->project_id = $feature->project_id;
                $row->save();
            }

            return count($spec['steps']);
        });

        return new FlowStepPathReplacementResult(
            path: $spec['path'],
            stepsWritten: $written,
        );
    }
}
