<?php

namespace App\Console\Commands;

use App\Data\Flowcharts\FlowchartStaleCheck;
use App\Enums\FeatureStatus;
use App\Models\Project;
use App\Services\Flowcharts\CheckFlowchartStaleness;
use Illuminate\Console\Command;

/**
 * Checks every flowchart node's `file`/`function` against the project's repo
 * (`Project.repo_path`), so a flowchart that drifted from the code
 * (files moved, functions renamed) gets caught before it misleads a reader.
 */
class CheckFlowchartsCommand extends Command
{
    protected $signature = 'flowcharts:check {project-slug} {--feature= : only this feature number}';

    protected $description = "Check a project's flowchart nodes against its repo";

    public function handle(CheckFlowchartStaleness $checkFlowchartStaleness): int
    {
        $project = Project::where('slug', $this->argument('project-slug'))->first();

        if (! $project) {
            $this->error("No project found with slug \"{$this->argument('project-slug')}\".");

            return self::FAILURE;
        }

        if (! $project->repo_path) {
            $this->error("Project \"{$project->slug}\" has no repo_path set.");

            return self::FAILURE;
        }

        $featureNumber = $this->option('feature') !== null ? (int) $this->option('feature') : null;
        $results = $checkFlowchartStaleness->check($project, $featureNumber);

        $checked = $results->sum(fn (FlowchartStaleCheck $result): int => $result->checkedNodes);
        $stale = $results->sum(fn (FlowchartStaleCheck $result): int => count($result->staleNodes));

        $plannedStatuses = [FeatureStatus::Todo, FeatureStatus::Uncertain];

        foreach ($results as $result) {
            if (in_array($result->flowchart->feature->status, $plannedStatuses, true)) {
                $this->line("功能 {$result->flowchart->feature->number} {$result->flowchart->feature->title}：计划中，跳过");

                continue;
            }

            foreach ($result->staleNodes as $node) {
                $this->line("功能 {$result->flowchart->feature->number} {$result->flowchart->feature->title}：{$node['id']} {$node['label']} {$node['file']} {$node['function']} — {$node['reason']}");
            }
        }

        if ($stale === 0) {
            $this->info("{$checked} 个节点核对通过");

            return self::SUCCESS;
        }

        $this->error("共 {$stale} 个节点过期，共 {$checked} 个");

        return self::FAILURE;
    }
}
