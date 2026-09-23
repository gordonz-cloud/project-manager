<?php

namespace App\Console\Commands;

use App\Models\ImplementationNode;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Checks every call-tree node's `file`/`function` against the project's repo
 * (`Project.repo_path`), so a data-flow page that drifted from the code
 * (files moved, functions renamed) gets caught before it misleads a reader.
 * Meant to run in CI.
 */
class CheckImplementationNodesCommand extends Command
{
    protected $signature = 'implementation-nodes:check {project-slug} {--entry= : only this entry, e.g. "POST /login" or a command}';

    protected $description = "Check a project's call-tree nodes against its repo";

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

        $steps = ImplementationNode::where('project_id', $project->id)
            ->whereNotNull('file')
            ->when($this->option('entry'), fn ($query, string $entry) => $query->whereHas(
                'requestReply',
                fn ($q) => $q->whereRaw("trim(coalesce(method, '') || ' ' || entry) = ?", [trim($entry)])
            ))
            ->with(['requestReply', 'feature'])
            ->orderBy('request_reply_id')
            ->orderBy('feature_id')
            ->orderBy('id')
            ->get();

        $stale = $steps->map(fn (ImplementationNode $step) => $this->checkStep($step, $project->repo_path))
            ->filter()
            ->values();

        if ($stale->isEmpty()) {
            $this->info("{$steps->count()} 行核对通过");

            return self::SUCCESS;
        }

        $stale->groupBy('owner')->each(function (Collection $rows, string $owner) {
            $this->line("{$owner}：");
            foreach ($rows as $row) {
                $this->line("  #{$row['id']} {$row['title']} {$row['file']} {$row['function']} — {$row['reason']}");
            }
        });

        $this->error("共 {$stale->count()} 行过期，共 {$steps->count()} 行");

        return self::FAILURE;
    }

    /**
     * @return array{owner: string, id: int, title: string, file: string, function: string, reason: string}|null
     */
    private function checkStep(ImplementationNode $step, string $repoPath): ?array
    {
        $filePath = preg_replace('/:\d+$/', '', (string) $step->file);
        $fullPath = rtrim($repoPath, '/').'/'.ltrim((string) $filePath, '/');

        $owner = $step->requestReply !== null
            ? "入口 {$step->requestReply->label()}"
            : "功能 {$step->feature?->number} {$step->feature?->title}";

        if (! File::exists($fullPath)) {
            return [
                'owner' => $owner,
                'id' => $step->id,
                'title' => $step->title,
                'file' => (string) $step->file,
                'function' => (string) $step->function,
                'reason' => '文件不存在',
            ];
        }

        $names = $this->functionNames((string) $step->function);

        if ($names === null || $names === []) {
            return null;
        }

        $contents = File::get($fullPath);
        $found = collect($names)->contains(fn (string $name) => $this->functionExists($contents, $name, $filePath));

        if (! $found) {
            return [
                'owner' => $owner,
                'id' => $step->id,
                'title' => $step->title,
                'file' => (string) $step->file,
                'function' => (string) $step->function,
                'reason' => '函数不存在',
            ];
        }

        return null;
    }

    /**
     * Splits a `function` field into candidate identifiers, or null when the
     * field is empty or reads as a description ("saved 钩子", "onClick
     * 回调") rather than a literal function name.
     *
     * @return array<int, string>|null
     */
    private function functionNames(string $function): ?array
    {
        $function = trim($function);

        if ($function === '') {
            return null;
        }

        if (preg_match('/\p{Han}/u', $function) === 1) {
            return null;
        }

        $parts = preg_split('/\s*\/\s*|\s*\|\s*/', $function) ?: [$function];

        return collect($parts)
            ->map(fn (string|false $name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->values()
            ->all();
    }

    private function functionExists(string $contents, string $name, string $filePath): bool
    {
        $quoted = preg_quote($name, '/');

        if (str_ends_with($filePath, '.php')) {
            return preg_match("/function\s+{$quoted}\s*\(/", $contents) === 1;
        }

        $patterns = [
            "/function\s+{$quoted}\s*\(/",
            "/const\s+{$quoted}\s*=/",
            "/\b{$quoted}\s*\(/",
            "/\b{$quoted}\s*:/",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                return true;
            }
        }

        return false;
    }
}
