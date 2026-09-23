<?php

namespace App\Console\Commands;

use App\Models\Feature;
use App\Models\Flowchart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LogicException;

/**
 * Writes one feature's flowchart from a JSON file:
 * {"project": "sg", "feature": 66, "chart": {"nodes": [...], "edges": [...]}, "pseudocode": "1. ..."}.
 */
class SaveFlowchartCommand extends Command
{
    protected $signature = 'flowcharts:save {file : JSON with project, feature, chart, pseudocode}';

    protected $description = "Create or replace a feature's flowchart from a JSON file";

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['feature'], $spec['chart'])) {
            $this->error("Expected {project, feature, chart, pseudocode?} in {$file}");

            return self::FAILURE;
        }

        $feature = Feature::withoutGlobalScopes()
            ->whereHas('project', fn ($query) => $query->where('slug', $spec['project']))
            ->where('number', (int) $spec['feature'])
            ->first();

        if ($feature === null) {
            $this->error("No feature {$spec['feature']} in project {$spec['project']}");

            return self::FAILURE;
        }

        try {
            Flowchart::withoutGlobalScopes()->updateOrCreate(
                ['feature_id' => $feature->id],
                ['chart' => $spec['chart'], 'pseudocode' => $spec['pseudocode'] ?? null],
            );
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Feature {$feature->number} flowchart saved: ".count($spec['chart']['nodes']).' nodes');

        return self::SUCCESS;
    }
}
