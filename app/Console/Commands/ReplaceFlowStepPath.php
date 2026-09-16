<?php

namespace App\Console\Commands;

use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $spec = File::exists($path) ? json_decode(File::get($path), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['feature'], $spec['path']) || ! is_array($spec['steps'] ?? null)) {
            $this->error("Expected {project, feature, path, steps[]} in {$path}");

            return self::FAILURE;
        }

        $project = Project::query()->where('slug', $spec['project'])->first();
        $feature = $project === null ? null : Feature::query()
            ->where('project_id', $project->id)->where('number', (int) $spec['feature'])->first();

        if ($feature === null) {
            $this->error("No feature {$spec['feature']} in project {$spec['project']}");

            return self::FAILURE;
        }

        foreach ($spec['steps'] as $index => $step) {
            foreach (['order', 'step', 'file', 'output'] as $required) {
                if (! isset($step[$required]) || $step[$required] === '') {
                    $this->error("steps[{$index}] is missing {$required}");

                    return self::FAILURE;
                }
            }
        }

        $written = DB::transaction(function () use ($feature, $spec): int {
            FlowStep::query()->where('feature_id', $feature->id)->where('path', $spec['path'])->delete();

            foreach ($spec['steps'] as $step) {
                // project_id is not fillable on purpose (the tenant sets it in
                // the panel); here the feature already says which project.
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

        $this->info("{$spec['path']}: {$written} steps now");

        return self::SUCCESS;
    }
}
