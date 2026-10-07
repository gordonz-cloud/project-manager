<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Requirements\RequirementTreeSaver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;

/**
 * Saves requirement-tree nodes from a JSON file. Omit "number" to create (the server assigns it; use "ref"/"parent_ref"
 * to link new nodes), give "number" to update an existing node:
 * {"project": "sg", "nodes": [{"number"?, "ref"?, "parent"?, "parent_ref"?, "kind", "title", "rationale", "source", "status",
 * "decided_by", "decided_at", "supersedes"?, "decider"? (Gordon|老板, only on 提议/冲突), "reason"?, "features": [feature numbers], "tests": [test numbers],
 * "depends_on"?: [requirement numbers it needs to hold], "depends_on_refs"?: [refs in this payload], "position"?: order among siblings,
 * "decision"?: {now, change, difference?, risk?, impact?, options: [{key, label, outcome: accept|reject|keep_current|custom, consequence, result_title?, recommended?}]}
 * (what Gordon is asked on the 待决策 page; see RequirementDecision)}]}.
 * Passing depends_on or depends_on_refs replaces the node's dependencies; omitting both leaves them alone.
 */
class SaveRequirementsCommand extends Command
{
    protected $signature = 'requirements:save {file : JSON with project and nodes} {--json : Print the result as JSON}';

    protected $description = 'Create or update requirement-tree nodes from a JSON file';

    public function handle(RequirementTreeSaver $requirementTreeSaver): int
    {
        $file = (string) $this->argument('file');
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;
        $project = is_array($spec) && isset($spec['project']) ? Project::query()->where('slug', $spec['project'])->first() : null;

        if (! is_array($spec) || ! is_array($spec['nodes'] ?? null) || $project === null) {
            $this->error("Expected {project: <existing slug>, nodes: [...]} in {$file}");

            return self::FAILURE;
        }

        try {
            $result = $requirementTreeSaver->save($project, $spec['nodes']);
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['created' => $result->created, 'updated' => $result->updated, 'assigned' => $result->assigned, 'voided' => $result->voided], JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach ($result->assigned as $label => $number) {
            $this->line("{$label} → #{$number}");
        }

        foreach ($result->voided as $number) {
            $this->line("#{$number} → 作废（被取代）");
        }

        $this->info("Requirements saved: {$result->created} created, {$result->updated} updated");

        return self::SUCCESS;
    }
}
