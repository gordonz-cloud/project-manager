<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Call trees (implementation_nodes + edges) become one flowchart per feature: chart JSON plus
 * numbered pseudocode. Node runs had no rows and nothing left to point at, so they go too.
 * One-way: the call-tree tables are gone after this.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convertCallTrees();

        Schema::table('commits', function (Blueprint $table) {
            $table->dropIndex(['implementation_node_id']);
            $table->dropConstrainedForeignId('implementation_node_id');
        });
        Schema::table('run_events', function (Blueprint $table) {
            $table->dropIndex(['node_run_id']);
            $table->dropConstrainedForeignId('node_run_id');
        });
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropIndex(['focus_node_run_id']);
            $table->dropColumn('focus_node_run_id');
        });
        Schema::dropIfExists('node_runs');
        Schema::dropIfExists('implementation_node_edges');
        Schema::dropIfExists('implementation_nodes');
    }

    /**
     * Each feature gets the function nodes it owns plus those of its linked entries;
     * an entry shared by several features is copied into each of them.
     */
    public function convertCallTrees(): void
    {
        $features = DB::table('features')->orderBy('id')->get(['id', 'project_id']);

        foreach ($features as $feature) {
            $nodes = DB::table('implementation_nodes')
                ->where('kind', 'function')
                ->where(fn ($query) => $query
                    ->where('feature_id', $feature->id)
                    ->orWhereIn('request_reply_id', DB::table('feature_request_reply')->where('feature_id', $feature->id)->select('request_reply_id')))
                ->orderBy('request_reply_id')
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            if ($nodes->isEmpty()) {
                continue;
            }

            $edges = DB::table('implementation_node_edges')
                ->whereIn('kind', ['calls', 'on_success', 'on_failure'])
                ->whereIn('from_node_id', $nodes->keys())
                ->whereIn('to_node_id', $nodes->keys())
                ->orderBy('id')
                ->get();

            DB::table('flowcharts')->insert([
                'project_id' => $feature->project_id,
                'feature_id' => $feature->id,
                'chart' => json_encode($this->chart($nodes, $edges), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pseudocode' => $this->pseudocode($nodes, $edges),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  Collection<int|string, stdClass>  $nodes
     * @param  Collection<int, stdClass>  $edges
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function chart(Collection $nodes, Collection $edges): array
    {
        $firstRoot = $this->roots($nodes, $edges)[0];

        return [
            'nodes' => array_values($nodes->map(fn (stdClass $node): array => array_filter([
                'id' => "n{$node->id}",
                'label' => $node->title,
                'shape' => $node->id === $firstRoot->id ? 'start' : 'step',
                'file' => $node->file,
                'function' => $node->function,
            ], fn (?string $value): bool => filled($value)))->all()),
            'edges' => array_values($edges->map(fn (stdClass $edge): array => array_filter([
                'from' => "n{$edge->from_node_id}",
                'to' => "n{$edge->to_node_id}",
                'kind' => $edge->kind === 'on_failure' ? 'failure' : 'next',
                'label' => $edge->kind === 'on_failure' ? $edge->condition : null,
            ], fn (?string $value): bool => filled($value)))->all()),
        ];
    }

    /**
     * Depth-first from the roots in call order; a failure branch comes right after its parent,
     * indented and led by its condition. A node is written once, so cycles end.
     *
     * @param  Collection<int|string, stdClass>  $nodes
     * @param  Collection<int, stdClass>  $edges
     */
    private function pseudocode(Collection $nodes, Collection $edges): string
    {
        $edgesByFrom = $edges->groupBy('from_node_id');
        $lines = [];
        $visited = [];

        $walk = function (stdClass $node, int $depth, ?string $condition) use (&$walk, &$lines, &$visited, $nodes, $edgesByFrom): void {
            if (isset($visited[$node->id])) {
                return;
            }

            $visited[$node->id] = true;
            $lines[] = str_repeat('   ', $depth).($condition === null ? '' : "若 {$condition}：").(count($lines) + 1).'. '.$this->step($node);
            [$failures, $calls] = collect($edgesByFrom[$node->id] ?? [])->partition(fn (stdClass $edge): bool => $edge->kind === 'on_failure');

            foreach ($failures as $edge) {
                $walk($nodes[$edge->to_node_id], $depth + 1, filled($edge->condition) ? $edge->condition : '失败');
            }

            foreach ($calls as $edge) {
                $walk($nodes[$edge->to_node_id], $depth, null);
            }
        };

        foreach ([...$this->roots($nodes, $edges), ...$nodes->values()->all()] as $root) {
            $walk($root, 0, null);
        }

        return implode("\n", $lines);
    }

    private function step(stdClass $node): string
    {
        $location = filled($node->file) ? $node->file.(filled($node->function) ? "::{$node->function}" : '') : $node->title;
        $change = $this->oneLine($node->change) ?: $node->title;
        $io = filled($node->input) || filled($node->output) ? ' ('.$this->oneLine($node->input).' → '.$this->oneLine($node->output).')' : '';

        return "{$location} — {$change}{$io}";
    }

    private function oneLine(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    /**
     * Nodes nothing calls, in id order; the first node when every node is called (a cycle).
     *
     * @param  Collection<int|string, stdClass>  $nodes
     * @param  Collection<int, stdClass>  $edges
     * @return list<stdClass>
     */
    private function roots(Collection $nodes, Collection $edges): array
    {
        $called = $edges->pluck('to_node_id')->flip();
        $roots = array_values($nodes->reject(fn (stdClass $node): bool => $called->has($node->id))->all());

        return $roots === [] ? [$nodes->firstOrFail()] : $roots;
    }
};
