<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Features\BackfillFeatureLayersFromTodos;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * One-off backfill: sets `layers` on existing features from the old Notion
 * "待办" (todo) database export. Matches, in order:
 *   1. an explicit `--map` file's `high` confidence entries (by feature number),
 *   2. exact title match, then title match with whitespace/punctuation stripped.
 * `low` confidence and `null` map entries are never applied — they, along
 * with anything unmatched by title, are listed for a human to sort. Never
 * overwrites a feature that already has layers (idempotent).
 */
class FeaturesBackfillFromTodosCommand extends Command
{
    protected $signature = 'features:backfill-from-todos {project-slug} {--file=storage/notion-export/todos.json} {--map=}';

    protected $description = 'Backfill feature layers from the exported Notion todos, matched by title or an explicit map';

    public function handle(BackfillFeatureLayersFromTodos $backfillFeatureLayersFromTodos): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        $file = (string) $this->option('file');

        try {
            $result = $backfillFeatureLayersFromTodos->handle(
                $project,
                $file,
                (string) $this->option('map'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        foreach ($result->lowConfidence as $lowConfidence) {
            $candidateText = $lowConfidence->candidateNumbers === []
                ? '(无候选)'
                : implode(', ', array_map(fn (int $number): string => "#{$number}", $lowConfidence->candidateNumbers));
            $this->line("  [low] {$lowConfidence->title} → {$candidateText} — {$lowConfidence->reason}");
        }

        foreach ($result->unmatchedTitles as $title) {
            $this->line("  [未匹配] {$title}");
        }

        return self::SUCCESS;
    }
}
