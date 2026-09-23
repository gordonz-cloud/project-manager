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

        foreach ($chart['nodes'] as $index => $node) {
            $id = is_array($node) ? ($node['id'] ?? null) : null;

            if (! is_string($id) || $id === '' || ! is_string($node['label'] ?? null)) {
                return "node {$index} needs a string id and label";
            }

            if (isset($ids[$id])) {
                return "duplicate node id {$id}";
            }

            if (! in_array($node['shape'] ?? null, self::SHAPES, true)) {
                return "node {$id} shape must be one of ".implode('|', self::SHAPES);
            }

            $ids[$id] = true;
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
