<?php

namespace App\Console\Commands;

use App\Enums\FeatureLayer;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * One-off backfill: sets `layer` and `version` on existing features from the
 * old Notion "待办" (todo) database export. Matches, in order:
 *   1. an explicit `--map` file's `high` confidence entries (by feature number),
 *   2. exact title match, then title match with whitespace/punctuation stripped.
 * `low` confidence and `null` map entries are never applied — they, along
 * with anything unmatched by title, are listed for a human to sort. Never
 * overwrites a feature that already has a value (idempotent).
 */
class FeaturesBackfillFromTodosCommand extends Command
{
    protected $signature = 'features:backfill-from-todos {project-slug} {--file=storage/notion-export/todos.json} {--map=}';

    protected $description = 'Backfill feature layer/version from the exported Notion todos, matched by title or an explicit map';

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

        $map = $this->loadMap($project);

        $features = Feature::where('project_id', $project->id)->get();
        $byExactTitle = $features->keyBy(fn (Feature $feature): string => $feature->title);
        $byNormalizedTitle = $features->keyBy(fn (Feature $feature): string => self::normalize($feature->title));

        $highApplied = 0;
        $unmatched = [];
        $low = [];

        foreach ($todos as $todo) {
            $title = (string) ($todo['待办'] ?? '');
            $mapEntry = $map[$title] ?? null;

            if ($mapEntry) {
                if ($mapEntry['confidence'] === 'high' && $mapEntry['features']->isNotEmpty()) {
                    foreach ($mapEntry['features'] as $feature) {
                        $this->applyTodo($feature, $todo);
                    }

                    $highApplied++;

                    continue;
                }

                $low[] = [$title, $mapEntry['features']->pluck('number')->all(), $mapEntry['why']];

                continue;
            }

            $feature = $byExactTitle->get($title) ?? $byNormalizedTitle->get(self::normalize($title));

            if (! $feature) {
                $unmatched[] = $title;

                continue;
            }

            $this->applyTodo($feature, $todo);
            $highApplied++;
        }

        $this->info("high 应用 {$highApplied}、low ".count($low).'、未匹配 '.count($unmatched));

        foreach ($low as [$title, $candidates, $why]) {
            $candidateText = $candidates === [] ? '(无候选)' : implode(', ', array_map(fn (int $n): string => "#{$n}", $candidates));
            $this->line("  [low] {$title} → {$candidateText} — {$why}");
        }

        foreach ($unmatched as $title) {
            $this->line("  [未匹配] {$title}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $todo
     */
    private function applyTodo(Feature $feature, array $todo): void
    {
        $feature->layer ??= FeatureLayer::from((string) $todo['层']);
        $feature->version ??= ($todo['MVP'] ?? null) === '__YES__' ? '1' : null;
        $feature->save();
    }

    /**
     * @return array<string, array{confidence: string, features: Collection<int, Feature>, why: string}>
     */
    private function loadMap(Project $project): array
    {
        $mapFile = (string) $this->option('map');

        if ($mapFile === '' || ! File::exists($mapFile)) {
            return [];
        }

        /** @var array<int, array{todo: string, feature: int|array<int, int>|null, confidence: string, why: string}> $rows */
        $rows = json_decode(File::get($mapFile), true);

        $featuresByNumber = Feature::where('project_id', $project->id)->get()->keyBy('number');

        $map = [];

        foreach ($rows as $row) {
            $numbers = is_array($row['feature']) ? $row['feature'] : array_filter([$row['feature']]);

            $map[$row['todo']] = [
                'confidence' => $row['confidence'],
                'features' => collect($numbers)->map(fn (int $number): ?Feature => $featuresByNumber->get($number))->filter()->values(),
                'why' => $row['why'],
            ];
        }

        return $map;
    }

    private static function normalize(string $title): string
    {
        return preg_replace('/[\s\p{P}]+/u', '', $title) ?? $title;
    }
}
