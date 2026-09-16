<?php

namespace App\Console\Commands;

use App\Enums\FeatureLayer;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * One-off backfill: sets `layer` and `version` on existing features from the
 * old Notion "待办" (todo) database export, matched by title. Never
 * overwrites a feature that already has a value (idempotent), and never
 * guesses at a todo it cannot match — those are listed for a human to sort.
 */
class FeaturesBackfillFromTodosCommand extends Command
{
    protected $signature = 'features:backfill-from-todos {project-slug} {--file=storage/notion-export/todos.json}';

    protected $description = 'Backfill feature layer/version from the exported Notion todos, matched by title';

    public function handle(): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        $file = (string) $this->option('file');

        if (! File::exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $todos */
        $todos = json_decode(File::get($file), true);

        $features = Feature::where('project_id', $project->id)->get();
        $byExactTitle = $features->keyBy(fn (Feature $feature): string => $feature->title);
        $byNormalizedTitle = $features->keyBy(fn (Feature $feature): string => self::normalize($feature->title));

        $matched = 0;
        $unmatched = [];

        foreach ($todos as $todo) {
            $title = (string) ($todo['待办'] ?? '');

            $feature = $byExactTitle->get($title) ?? $byNormalizedTitle->get(self::normalize($title));

            if (! $feature) {
                $unmatched[] = $title;

                continue;
            }

            $matched++;

            $feature->layer ??= FeatureLayer::from((string) $todo['层']);
            $feature->version ??= ($todo['MVP'] ?? null) === '__YES__' ? '1' : null;
            $feature->save();
        }

        $this->info("匹配 {$matched}、未匹配 ".count($unmatched));

        foreach ($unmatched as $title) {
            $this->line("  - {$title}");
        }

        return self::SUCCESS;
    }

    private static function normalize(string $title): string
    {
        return preg_replace('/[\s\p{P}]+/u', '', $title) ?? $title;
    }
}
