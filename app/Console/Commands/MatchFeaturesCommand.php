<?php

namespace App\Console\Commands;

use App\Data\Features\ChangeToMatch;
use App\Data\Features\FeatureMatch;
use App\Models\Project;
use App\Services\Features\MatchFeaturesToChange;
use Illuminate\Console\Command;

/**
 * Ranks a project's existing features by how likely a pending change (files,
 * entry, or free text) belongs to them, so a change lands on the right
 * feature instead of spawning a duplicate.
 */
class MatchFeaturesCommand extends Command
{
    protected $signature = 'features:match {project-slug} {--files= : comma-separated changed file paths} {--entry= : e.g. "POST /login"} {--text= : free-text description} {--limit=10}';

    protected $description = "Rank a project's features by likelihood a change belongs to them";

    public function handle(MatchFeaturesToChange $matcher): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        $change = new ChangeToMatch(
            files: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('files'))))),
            entry: $this->option('entry') !== null ? (string) $this->option('entry') : null,
            text: $this->option('text') !== null ? (string) $this->option('text') : null,
        );

        $matches = $matcher->handle($project, $change, (int) $this->option('limit'));

        if ($matches->isEmpty()) {
            $this->info('没有匹配的功能');

            return self::SUCCESS;
        }

        $this->table(
            ['score', '编号', '标题', 'use case', '状态', 'why'],
            $matches->map(fn (FeatureMatch $match): array => [
                $match->score,
                $match->feature->number,
                $match->feature->title,
                $match->feature->useCase?->goal,
                $match->feature->status->value,
                $match->why(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
