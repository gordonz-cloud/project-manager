<?php

namespace App\Services\Flowcharts;

use App\Data\Flowcharts\FlowchartStaleCheck;
use App\Models\Flowchart;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Checks every flowchart node's `file`/`function` against the project's repo
 * (`Project.repo_path`), so a flowchart that drifted from the code (files
 * moved, functions renamed) gets caught before it misleads a reader. Writes
 * the result onto each flowchart (`stale_checked_at`, `stale_nodes`), so the
 * workbench can show it without anyone running a command by hand.
 */
class CheckFlowchartStaleness
{
    /**
     * @return Collection<int, FlowchartStaleCheck>
     */
    public function check(Project $project, ?int $featureNumber = null): Collection
    {
        $repoPath = (string) $project->repo_path;

        return Flowchart::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->when($featureNumber, fn ($query, int $number) => $query->whereHas('feature', fn ($q) => $q->where('number', $number)))
            ->with('feature')
            ->get()
            ->map(fn (Flowchart $flowchart): FlowchartStaleCheck => $this->checkFlowchart($flowchart, $repoPath));
    }

    private function checkFlowchart(Flowchart $flowchart, string $repoPath): FlowchartStaleCheck
    {
        $checked = 0;
        $staleNodes = [];

        foreach ($flowchart->chart['nodes'] as $node) {
            if (blank($node['file'] ?? null)) {
                continue;
            }

            $checked++;
            $reason = $this->staleReason((string) $node['file'], (string) ($node['function'] ?? ''), $repoPath);

            if ($reason !== null) {
                $staleNodes[] = [
                    'id' => $node['id'],
                    'label' => $node['label'],
                    'file' => (string) $node['file'],
                    'function' => (string) ($node['function'] ?? ''),
                    'reason' => $reason,
                ];
            }
        }

        $flowchart->forceFill([
            'stale_checked_at' => Carbon::now(),
            'stale_nodes' => array_column($staleNodes, 'id'),
        ])->save();

        return new FlowchartStaleCheck($flowchart, $checked, $staleNodes);
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
