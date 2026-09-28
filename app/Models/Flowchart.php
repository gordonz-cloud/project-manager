<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\FlowchartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A feature's flowchart: what the code does, as a chart plus numbered pseudocode.
 *
 * @property int $id
 * @property int $project_id
 * @property int $feature_id
 * @property array{nodes: list<array{id: string, label: string, shape: string, file?: string, function?: string}>, edges: list<array{from: string, to: string, label?: string, kind?: string}>} $chart
 * @property string|null $pseudocode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['feature_id', 'chart', 'pseudocode'])]
class Flowchart extends Model
{
    /** @use HasFactory<FlowchartFactory> */
    use BelongsToProject, HasFactory;

    public const SHAPES = ['start', 'step', 'decision', 'end', 'io'];

    public const EDGE_KINDS = ['next', 'failure'];

    public const MAX_LABEL_LENGTH = 24;

    protected static function booted(): void
    {
        static::saving(function (self $flowchart): void {
            $error = self::chartError($flowchart->chart);

            if ($error !== null) {
                throw new LogicException("Invalid flowchart: {$error}");
            }

            $feature = Feature::withoutGlobalScopes()->find($flowchart->feature_id)
                ?? throw new LogicException('A flowchart feature must exist.');
            $flowchart->project_id = $feature->project_id;
        });
    }

    /**
     * Why a chart is malformed, or null when it is fine.
     */
    public static function chartError(mixed $chart): ?string
    {
        if (! is_array($chart) || ! is_array($chart['nodes'] ?? null) || ! array_is_list($chart['nodes']) || ! is_array($chart['edges'] ?? []) || ! array_is_list($chart['edges'] ?? [])) {
            return 'expected {"nodes": [...], "edges": [...]}';
        }

        $ids = [];
        $nodes = [];
        $edges = [];

        foreach ($chart['nodes'] as $index => $node) {
            $id = is_array($node) ? ($node['id'] ?? null) : null;
            $label = is_array($node) ? ($node['label'] ?? null) : null;
            $shape = is_array($node) ? ($node['shape'] ?? null) : null;

            if (! is_string($id) || $id === '' || ! is_string($label)) {
                return "node {$index} needs a string id and label";
            }

            if (isset($ids[$id])) {
                return "duplicate node id {$id}";
            }

            if (! is_string($shape) || ! in_array($shape, self::SHAPES, true)) {
                return "node {$id} shape must be one of ".implode('|', self::SHAPES);
            }

            $ids[$id] = true;
            $nodes[] = ['id' => $id, 'label' => $label, 'shape' => $shape];
        }

        foreach ($chart['edges'] ?? [] as $index => $edge) {
            $from = is_array($edge) ? ($edge['from'] ?? null) : null;
            $to = is_array($edge) ? ($edge['to'] ?? null) : null;

            if (! is_string($from) || ! is_string($to) || ! isset($ids[$from], $ids[$to])) {
                return "edge {$index} must connect existing nodes";
            }

            if (! in_array($edge['kind'] ?? 'next', self::EDGE_KINDS, true)) {
                return "edge {$index} kind must be one of ".implode('|', self::EDGE_KINDS);
            }

            $edges[] = ['from' => $from, 'to' => $to, 'label' => is_string($edge['label'] ?? null) ? $edge['label'] : ''];
        }

        return self::businessRuleError($nodes, $edges);
    }

    /**
     * Why a structurally sound chart breaks the drawing rules (at least one start, ends, decisions own forks, short labels, all reachable from some start).
     *
     * @param  list<array{id: string, label: string, shape: string}>  $nodes
     * @param  list<array{from: string, to: string, label: string}>  $edges
     */
    private static function businessRuleError(array $nodes, array $edges): ?string
    {

        $starts = array_values(array_filter($nodes, fn (array $node): bool => $node['shape'] === 'start'));

        if (count($starts) < 1) {
            return '至少要有一个 start 节点';
        }

        $shapes = array_column($nodes, 'shape', 'id');

        if (! in_array('end', $shapes, true)) {
            return '至少要有一个 end 节点';
        }

        foreach ($nodes as $node) {
            if ($node['shape'] !== 'start' && mb_strlen($node['label']) > self::MAX_LABEL_LENGTH) {
                return "节点 {$node['id']} 的 label 超过 ".self::MAX_LABEL_LENGTH.' 字';
            }
        }

        $outgoing = [];

        foreach ($edges as $edge) {
            $outgoing[$edge['from']][] = $edge;
        }

        foreach ($outgoing as $from => $fromEdges) {
            if (count($fromEdges) > 1 && $shapes[$from] !== 'decision') {
                return "节点 {$from} 有多条出边，分叉只能从 decision 出";
            }

            if ($shapes[$from] === 'decision' && ! collect($fromEdges)->every(fn (array $edge): bool => trim($edge['label']) !== '')) {
                return "decision 节点 {$from} 的每条出边都要带 label";
            }
        }

        $reached = array_fill_keys(array_column($starts, 'id'), true);
        $queue = array_column($starts, 'id');

        while ($queue !== []) {
            foreach ($outgoing[array_shift($queue)] ?? [] as $edge) {
                if (! isset($reached[$edge['to']])) {
                    $reached[$edge['to']] = true;
                    $queue[] = $edge['to'];
                }
            }
        }

        foreach (array_keys($shapes) as $id) {
            if (! isset($reached[$id])) {
                return "节点 {$id} 从 start 走不到";
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chart' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
