<?php

namespace App\Console\Commands;

use App\Models\Flowchart;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Checks every flowchart node's `file`/`function` against the project's repo
 * (`Project.repo_path`), so a flowchart that drifted from the code
 * (files moved, functions renamed) gets caught before it misleads a reader.
 */
class CheckFlowchartsCommand extends Command
{
    protected $signature = 'flowcharts:check {project-slug} {--feature= : only this feature number}';

    protected $description = "Check a project's flowchart nodes against its repo";

    public function handle(): int
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

        $flowcharts = Flowchart::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->when($this->option('feature'), fn ($query, string $number) => $query->whereHas('feature', fn ($q) => $q->where('number', (int) $number)))
            ->with('feature')
            ->get();
        $checked = 0;
        $stale = 0;

        foreach ($flowcharts as $flowchart) {
            foreach ($flowchart->chart['nodes'] as $node) {
                if (blank($node['file'] ?? null)) {
                    continue;
                }

                $checked++;
                $reason = $this->staleReason((string) $node['file'], (string) ($node['function'] ?? ''), $project->repo_path);

                if ($reason !== null) {
                    $stale++;
                    $this->line("功能 {$flowchart->feature->number} {$flowchart->feature->title}：{$node['id']} {$node['label']} {$node['file']} ".($node['function'] ?? '')." — {$reason}");
                }
            }
        }

        if ($stale === 0) {
            $this->info("{$checked} 个节点核对通过");

            return self::SUCCESS;
        }

        $this->error("共 {$stale} 个节点过期，共 {$checked} 个");

        return self::FAILURE;
    }

    private function staleReason(string $file, string $function, string $repoPath): ?string
    {
        $filePath = (string) preg_replace('/:\d+$/', '', $file);
        $fullPath = rtrim($repoPath, '/').'/'.ltrim($filePath, '/');

        if (! File::exists($fullPath)) {
            return '文件不存在';
        }

        $names = $this->functionNames($function);

        if ($names === []) {
            return null;
        }

        $contents = File::get($fullPath);

        return collect($names)->contains(fn (string $name) => $this->functionExists($contents, $name, $filePath)) ? null : '函数不存在';
    }

    /**
     * Candidate identifiers in a `function` field ("store / update"); none when it is empty or
     * reads as a description ("saved 钩子") rather than a literal function name.
     *
     * @return list<string>
     */
    private function functionNames(string $function): array
    {
        $function = trim($function);

        if ($function === '' || preg_match('/\p{Han}/u', $function) === 1) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\s*[\/|]\s*/', $function) ?: [$function])));
    }

    private function functionExists(string $contents, string $name, string $filePath): bool
    {
        $quoted = preg_quote($name, '/');
        $patterns = str_ends_with($filePath, '.php')
            ? ["/function\s+{$quoted}\s*\(/"]
            : ["/function\s+{$quoted}\s*\(/", "/const\s+{$quoted}\s*=/", "/\b{$quoted}\s*\(/", "/\b{$quoted}\s*:/"];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                return true;
            }
        }

        return false;
    }
}
