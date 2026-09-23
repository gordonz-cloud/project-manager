<?php

namespace App\Services\Features;

use App\Data\Features\FeatureLayerBackfillLowConfidence;
use App\Data\Features\FeatureLayerBackfillResult;
use App\Enums\FeatureLayer;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class BackfillFeatureLayersFromTodos
{
    public function handle(Project $project, string $file, string $mapFile): FeatureLayerBackfillResult
    {
        if (! File::exists($file)) {
            throw new InvalidArgumentException("File not found: {$file}");
        }

        /** @var array<int, array<string, mixed>> $todos */
        $todos = json_decode(File::get($file), true);
        $map = $this->loadMap($project, $mapFile);
        $features = Feature::query()->where('project_id', $project->id)->get();
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

                $low[] = new FeatureLayerBackfillLowConfidence(
                    title: $title,
                    candidateNumbers: array_values(
                        $mapEntry['features']
                            ->pluck('number')
                            ->map(fn (int|string $number): int => (int) $number)
                            ->all(),
                    ),
                    reason: $mapEntry['why'],
                );

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

        return new FeatureLayerBackfillResult(
            highApplied: $highApplied,
            lowConfidence: $low,
            unmatchedTitles: $unmatched,
        );
    }

    /**
     * @param  array<string, mixed>  $todo
     */
    private function applyTodo(Feature $feature, array $todo): void
    {
        if ($feature->layers === null || $feature->layers === []) {
            $feature->layers = [FeatureLayer::from((string) $todo['层'])->value];
            $feature->save();
        }
    }

    /**
     * @return array<string, array{confidence: string, features: Collection<int, Feature>, why: string}>
     */
    private function loadMap(Project $project, string $mapFile): array
    {
        if ($mapFile === '' || ! File::exists($mapFile)) {
            return [];
        }

        /** @var array<int, array{todo: string, feature: int|array<int, int>|null, confidence: string, why: string}> $rows */
        $rows = json_decode(File::get($mapFile), true);
        $featuresByNumber = Feature::query()->where('project_id', $project->id)->get()->keyBy('number');
        $map = [];

        foreach ($rows as $row) {
            $numbers = is_array($row['feature']) ? $row['feature'] : array_filter([$row['feature']]);

            $map[$row['todo']] = [
                'confidence' => $row['confidence'],
                'features' => collect($numbers)
                    ->map(fn (int $number): ?Feature => $featuresByNumber->get($number))
                    ->filter()
                    ->values(),
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
