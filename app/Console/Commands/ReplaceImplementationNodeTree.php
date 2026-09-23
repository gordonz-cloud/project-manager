<?php

namespace App\Console\Commands;

use App\Services\ImplementationNodes\ReplaceRequestReplyCallTree;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Replaces one entry's call tree from a JSON file; see ReplaceRequestReplyCallTree for the format.
 */
class ReplaceImplementationNodeTree extends Command
{
    protected $signature = 'implementation-nodes:replace-tree {file : JSON describing one entry\'s call tree}';

    protected $description = "Replace one entry's whole call tree from a JSON file";

    public function handle(ReplaceRequestReplyCallTree $replaceCallTree): int
    {
        try {
            $written = $replaceCallTree->handle((string) $this->argument('file'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$written} nodes now");

        return self::SUCCESS;
    }
}
