<?php

namespace App\Services\Features;

use App\Data\Features\ChangeToMatch;
use App\Data\Features\FeatureMatch;
use App\Enums\FeatureStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\RequestReply;
use Illuminate\Support\Collection;

/**
 * Ranks a project's non-void features by how likely a pending change belongs to
 * them: shared flowchart node files outrank a matching entry, which outranks
 * shared words between the change and the feature's title/pseudocode.
 */
class MatchFeaturesToChange
{
    private const FILE_MATCH_SCORE = 100;

    private const ENTRY_MATCH_SCORE = 20;

    private const WORD_MATCH_SCORE = 1;

    /**
     * @return Collection<int, FeatureMatch>
     */
    public function handle(Project $project, ChangeToMatch $change, int $limit = 10): Collection
    {
        $features = Feature::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', FeatureStatus::Void)
            ->with(['flowchart', 'requestReplies', 'useCase'])
            ->get();

        return $features
            ->map(fn (Feature $feature): FeatureMatch => $this->score($feature, $change))
            ->filter(fn (FeatureMatch $match): bool => $match->score > 0)
            ->sortByDesc(fn (FeatureMatch $match): int => $match->score)
            ->take($limit)
            ->values();
    }

    private function score(Feature $feature, ChangeToMatch $change): FeatureMatch
    {
        $matchedFiles = $this->matchedFiles($feature, $change->files);
        $entryMatched = $this->entryMatches($feature, $change->entry);
        $matchedWords = $this->matchedWords($feature, $change->text);

        $score = count($matchedFiles) * self::FILE_MATCH_SCORE
            + ($entryMatched ? self::ENTRY_MATCH_SCORE : 0)
            + count($matchedWords) * self::WORD_MATCH_SCORE;

        return new FeatureMatch($feature, $score, $matchedFiles, $entryMatched, $matchedWords);
    }

    /**
     * @param  list<string>  $changedFiles
     * @return list<string>
     */
    private function matchedFiles(Feature $feature, array $changedFiles): array
    {
        if ($changedFiles === [] || $feature->flowchart === null) {
            return [];
        }

        $nodeFiles = collect($feature->flowchart->chart['nodes'])
            ->pluck('file')
            ->filter()
            ->unique()
            ->filter(fn (string $nodeFile): bool => collect($changedFiles)->contains(
                fn (string $changedFile): bool => $this->pathsOverlap($nodeFile, $changedFile)
            ))
            ->values()
            ->all();

        return array_values($nodeFiles);
    }

    private function pathsOverlap(string $nodeFile, string $changedFile): bool
    {
        $nodeFile = ltrim((string) preg_replace('/:\d+$/', '', $nodeFile), '/');
        $changedFile = ltrim((string) preg_replace('/:\d+$/', '', $changedFile), '/');

        return $nodeFile !== '' && $changedFile !== ''
            && (str_ends_with($nodeFile, $changedFile) || str_ends_with($changedFile, $nodeFile));
    }

    private function entryMatches(Feature $feature, ?string $entry): bool
    {
        if (blank($entry)) {
            return false;
        }

        $candidates = $feature->requestReplies
            ->map(fn (RequestReply $requestReply): string => $requestReply->label())
            ->push((string) $feature->entry)
            ->filter();

        return $candidates->contains(fn (string $candidate): bool => $this->normalizeEntry($candidate) === $this->normalizeEntry($entry));
    }

    private function normalizeEntry(string $entry): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $entry)));
    }

    /**
     * @return list<string>
     */
    private function matchedWords(Feature $feature, ?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $featureText = $feature->title.' '.($feature->flowchart->pseudocode ?? '');

        return array_values($this->words($text)->intersect($this->words($featureText))->values()->all());
    }

    /**
     * Chinese bigrams plus latin words, both lowercased/deduped, so "扫码枪" and
     * "枪扫码支付" share "扫码" without a full segmenter.
     *
     * @return Collection<int, string>
     */
    private function words(string $text): Collection
    {
        preg_match_all('/\p{Han}/u', $text, $hanMatches);
        $han = $hanMatches[0];

        $bigrams = [];

        for ($i = 0; $i < count($han) - 1; $i++) {
            $bigrams[] = $han[$i].$han[$i + 1];
        }

        preg_match_all('/[a-zA-Z]{2,}/', $text, $latinMatches);
        $latin = array_map('strtolower', $latinMatches[0]);

        return collect([...$bigrams, ...$latin])->unique();
    }
}
