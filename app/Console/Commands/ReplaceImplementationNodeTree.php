<?php

namespace App\Console\Commands;

use App\Services\ImplementationNodes\ReplaceFeatureCallTree;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Replaces one entry's call tree from a JSON file; see ReplaceFeatureCallTree for the format.
 */
class ReplaceImplementationNodeTree extends Command
{
    protected $signature = 'implementation-nodes:replace-tree {file : JSON describing one entry\'s call tree}';

    protected $description = "Replace one entry's whole call tree from a JSON file";

    public function handle(ReplaceFeatureCallTree $replaceFeatureCallTree): int
    {
        try {
            $written = $replaceFeatureCallTree->handle((string) $this->argument('file'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$written} nodes now");

        return self::SUCCESS;
    }
}
