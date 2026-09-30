<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tests\TestTreeSaver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;

/**
 * Upserts Test Matrix nodes from a JSON file:
 * {"project": "sg", "nodes": [{"number", "parent", "module", "action", "expected", "priority", "platform",
 * "test_file", "test_name", "auto", "result", "notes", "features": [feature numbers]}]}.
 */
class SaveTestsCommand extends Command
{
    protected $signature = 'tests:save {file : JSON with project and nodes}';

    protected $description = 'Create or update Test Matrix nodes from a JSON file';

    public function handle(TestTreeSaver $testTreeSaver): int
    {
        $file = (string) $this->argument('file');
        $spec = File::exists($file) ? json_decode(File::get($file), true) : null;
        $project = is_array($spec) && isset($spec['project']) ? Project::query()->where('slug', $spec['project'])->first() : null;

        if (! is_array($spec) || ! is_array($spec['nodes'] ?? null) || $project === null) {
            $this->error("Expected {project: <existing slug>, nodes: [...]} in {$file}");

            return self::FAILURE;
        }

        try {
            $result = $testTreeSaver->save($project, $spec['nodes']);
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Tests saved: {$result->created} created, {$result->updated} updated, {$result->nodesWithFeaturesSynced} feature lists synced");

        return self::SUCCESS;
    }
}
