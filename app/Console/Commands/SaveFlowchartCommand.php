<?php

namespace App\Console\Commands;

use App\Models\Flowchart;
use App\Models\Requirement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LogicException;

/**
 * Writes one rule's flowchart from a JSON file:
 * {"project": "sg", "requirement": 120, "chart": {"nodes": [...], "edges": [...]}, "pseudocode": "1. ..."}.
 */
class SaveFlowchartCommand extends Command
{
    protected $signature = 'flowcharts:save {file : JSON with project, requirement, chart, pseudocode}';

    protected $description = "Create or replace a rule's flowchart from a JSON file";

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;

        if (! is_array($spec) || ! isset($spec['project'], $spec['requirement'], $spec['chart'])) {
            $this->error("Expected {project, requirement, chart, pseudocode?} in {$file}");

            return self::FAILURE;
        }

        $rule = Requirement::withoutGlobalScopes()
            ->whereHas('project', fn ($query) => $query->where('slug', $spec['project']))
            ->where('number', (int) $spec['requirement'])
            ->first();

        if ($rule === null) {
            $this->error("No requirement {$spec['requirement']} in project {$spec['project']}");

            return self::FAILURE;
        }

        try {
            Flowchart::withoutGlobalScopes()->updateOrCreate(
                ['requirement_id' => $rule->id],
                ['chart' => $spec['chart'], 'pseudocode' => $spec['pseudocode'] ?? null],
            );
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Requirement {$rule->number} flowchart saved: ".count($spec['chart']['nodes']).' nodes');

        return self::SUCCESS;
    }
}
