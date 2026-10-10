<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tests\TestTreeSaver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;

/**
 * Saves Test Matrix nodes from a JSON file. Omit "number" to create (the server assigns it; use "ref"/"parent_ref"
 * to link new nodes), give "number" to update an existing node:
 * {"project": "sg", "nodes": [{"number"?, "ref"?, "parent"?, "parent_ref"?, "module", "action", "expected", "priority", "platform",
 * "test_file", "test_name", "auto", "result", "notes"}]}. Rules point at tests (requirements:save "tests"), not the other way round.
 */
class SaveTestsCommand extends Command
{
    protected $signature = 'tests:save {file : JSON with project and nodes} {--json : Print the result as JSON}';

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

        if ($this->option('json')) {
            $this->line((string) json_encode(['created' => $result->created, 'updated' => $result->updated, 'assigned' => $result->assigned], JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach ($result->assigned as $label => $number) {
            $this->line("{$label} → #{$number}");
        }

        $this->info("Tests saved: {$result->created} created, {$result->updated} updated");

        return self::SUCCESS;
    }
}
