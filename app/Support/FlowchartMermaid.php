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
            $location = trim(($node['file'] ?? '').(filled($node['function'] ?? null) ? '::'.$node['function'] : ''), ':');
            $label = self::escape($node['label']).($location === '' ? '' : '<br>'.self::escape($location));
            $lines[] = "    n{$index}{$open}\"{$label}\"{$close}";
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

    private static function escape(string $text): string
    {
        return str_replace(['#', '"', '<', '>', "\n"], ['#35;', '#quot;', '#lt;', '#gt;', ' '], $text);
    }
}
