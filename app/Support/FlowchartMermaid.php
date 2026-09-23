<?php

namespace App\Support;

use App\Models\Flowchart;

/**
 * The one place a flowchart's chart JSON becomes Mermaid source.
 * Node ids are renumbered, so ids like "end" (a Mermaid keyword) cannot break the chart.
 */
class FlowchartMermaid
{
    private const SHAPES = [
        'start' => ['([', '])'],
        'end' => ['([', '])'],
        'decision' => ['{', '}'],
        'io' => ['[/', '/]'],
        'step' => ['[', ']'],
    ];

    private const FAILURE_STYLE = 'stroke:#dc2626,color:#dc2626';

    public static function fromFlowchart(Flowchart $flowchart): string
    {
        $mermaidIds = [];
        $lines = ['flowchart TD'];

        foreach ($flowchart->chart['nodes'] as $index => $node) {
            $mermaidIds[$node['id']] = "n{$index}";
            [$open, $close] = self::SHAPES[$node['shape']];
            $lines[] = "    n{$index}{$open}\"".self::nodeText($node)."\"{$close}";
        }

        $failureLinks = [];

        foreach ($flowchart->chart['edges'] as $index => $edge) {
            $label = filled($edge['label'] ?? null) ? '|"'.self::escape($edge['label']).'"|' : '';
            $lines[] = "    {$mermaidIds[$edge['from']]} -->{$label} {$mermaidIds[$edge['to']]}";

            if (($edge['kind'] ?? 'next') === 'failure') {
                $failureLinks[] = $index;
            }
        }

        if ($failureLinks !== []) {
            $lines[] = '    linkStyle '.implode(',', $failureLinks).' '.self::FAILURE_STYLE;
        }

        return implode("\n", $lines);
    }

    /**
     * Each Mermaid node id's file::function, shown as a hover tooltip instead of in the box.
     *
     * @return array<string, string>
     */
    public static function tooltips(Flowchart $flowchart): array
    {
        $tooltips = [];

        foreach ($flowchart->chart['nodes'] as $index => $node) {
            $location = trim(($node['file'] ?? '').(filled($node['function'] ?? null) ? '::'.$node['function'] : ''), ':');

            if ($location !== '') {
                $tooltips["n{$index}"] = $location;
            }
        }

        return $tooltips;
    }

    /**
     * @param  array{label: string, file?: string|null, function?: string|null}  $node
     */
    private static function nodeText(array $node): string
    {
        $ref = self::codeRef($node);

        if ($ref === null) {
            return self::escape($node['label']);
        }

        return self::escape($ref).'<br/>'.self::escape($node['label']);
    }

    /**
     * Short "ClassName::function" reference for a node's file/function, or null when it has neither.
     *
     * @param  array{file?: string|null, function?: string|null}  $node
     */
    private static function codeRef(array $node): ?string
    {
        $file = filled($node['file'] ?? null) ? pathinfo($node['file'], PATHINFO_FILENAME) : null;
        $function = $node['function'] ?? null;

        return match (true) {
            filled($file) && filled($function) => "{$file}::{$function}",
            filled($file) => $file,
            filled($function) => $function,
            default => null,
        };
    }

    private static function escape(string $text): string
    {
        return str_replace(['#', '"', '<', '>', "\n"], ['#35;', '#quot;', '#lt;', '#gt;', ' '], $text);
    }
}
