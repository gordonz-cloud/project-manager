<?php

namespace App\Console\Commands;

use App\Models\Feature;
use App\Models\Flowchart;
use App\Models\Requirement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LogicException;

/**
 * Writes one feature's or one rule's flowchart from a JSON file:
 * {"project": "sg", "feature": 66 | "requirement": 120, "chart": {"nodes": [...], "edges": [...]}, "pseudocode": "1. ..."}.
 */
class SaveFlowchartCommand extends Command
{
    protected $signature = 'flowcharts:save {file : JSON with project, feature or requirement, chart, pseudocode}';

    protected $description = "Create or replace a feature's or a rule's flowchart from a JSON file";

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['chart']) || isset($spec['feature']) === isset($spec['requirement'])) {
            $this->error("Expected {project, feature | requirement, chart, pseudocode?} in {$file}");

            return self::FAILURE;
        }

        $ownerKey = isset($spec['feature']) ? 'feature' : 'requirement';
        $ownerClass = $ownerKey === 'feature' ? Feature::class : Requirement::class;
        $owner = $ownerClass::withoutGlobalScopes()
            ->whereHas('project', fn ($query) => $query->where('slug', $spec['project']))
            ->where('number', (int) $spec[$ownerKey])
            ->first();

        if ($owner === null) {
            $this->error("No {$ownerKey} {$spec[$ownerKey]} in project {$spec['project']}");

            return self::FAILURE;
        }

        try {
            Flowchart::withoutGlobalScopes()->updateOrCreate(
                ["{$ownerKey}_id" => $owner->id],
                ['chart' => $spec['chart'], 'pseudocode' => $spec['pseudocode'] ?? null],
            );
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $label = $ownerKey === 'feature' ? 'Feature' : 'Requirement';
        $this->info("{$label} {$owner->number} flowchart saved: ".count($spec['chart']['nodes']).' nodes');

        return self::SUCCESS;
    }
}
