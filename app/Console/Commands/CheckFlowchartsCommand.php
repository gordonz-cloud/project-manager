<?php

namespace App\Console\Commands;

use App\Data\Flowcharts\FlowchartStaleCheck;
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
    protected $signature = 'flowcharts:check {project-slug} {--requirement= : only this rule number}';

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

        $requirementNumber = $this->option('requirement') !== null ? (int) $this->option('requirement') : null;
        $results = $checkFlowchartStaleness->check($project, $requirementNumber);

        $checked = $results->sum(fn (FlowchartStaleCheck $result): int => $result->checkedNodes);
        $stale = $results->sum(fn (FlowchartStaleCheck $result): int => count($result->staleNodes));

        foreach ($results as $result) {
            if ($checkFlowchartStaleness->isPlanned($result->flowchart)) {
                $this->line("规则 {$result->flowchart->requirement->number} {$result->flowchart->requirement->title}：计划中，跳过");

                continue;
            }

            foreach ($result->staleNodes as $node) {
                $this->line("规则 {$result->flowchart->requirement->number} {$result->flowchart->requirement->title}：{$node['id']} {$node['label']} {$node['file']} {$node['function']} — {$node['reason']}");
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
