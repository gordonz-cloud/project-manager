<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The shared part of the tree save commands (tests:save, requirements:save): new nodes come without "number" and get
 * the next free one, "ref"/"parent_ref" let new nodes in one payload point at each other, and parents must not loop.
 */
final class NumberedTreePayload
{
    /**
     * Gives new nodes the next free numbers and turns parent_ref into parent; unknown numbers or refs reject the payload.
     *
     * @template TNode of array{number?: int, ref?: string, parent_ref?: string, parent?: int|null}
     *
     * @param  list<TNode>  $rawNodes
     * @param  list<int>  $existingNumbers
     * @return array{list<array<string, mixed>>, array<string, int>} nodes (each with number, no ref/parent_ref), and ref (or "row N") => assigned number
     */
    public static function numbered(array $rawNodes, array $existingNumbers, string $noun): array
    {
        $next = ($existingNumbers === [] ? 0 : max($existingNumbers)) + 1;
        $known = array_flip($existingNumbers);
        $numberByRef = [];
        $assigned = [];
        $errors = [];

        foreach ($rawNodes as $index => $node) {
            if (isset($node['number']) && ! isset($known[$node['number']])) {
                $errors[] = "#{$node['number']}: no such {$noun}; omit number to create a new node.";
            }

            $number = $node['number'] ?? $next++;

            if (! isset($node['number'])) {
                $assigned[$node['ref'] ?? "row {$index}"] = $number;
            }

            if (isset($node['ref'])) {
                if (isset($numberByRef[$node['ref']])) {
                    $errors[] = "ref \"{$node['ref']}\" is used twice.";
                }

                $numberByRef[$node['ref']] = $number;
            }

            $rawNodes[$index]['number'] = $number;
        }

        $nodes = [];

        foreach ($rawNodes as $node) {
            if (isset($node['parent_ref'])) {
                if (! isset($numberByRef[$node['parent_ref']])) {
                    $errors[] = "#{$node['number']}: parent_ref \"{$node['parent_ref']}\" matches no ref in this payload.";
                }

                $node['parent'] = $numberByRef[$node['parent_ref']] ?? null;
            }

            unset($node['ref'], $node['parent_ref']);
            $nodes[] = $node;
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return [$nodes, $assigned];
    }

    /**
     * @param  array<int, int|null>  $parents  number => parent number, after the payload is applied
     * @return list<string>
     */
    public static function parentErrors(array $parents): array
    {
        $errors = [];

        foreach ($parents as $number => $parent) {
            if ($parent !== null && ! array_key_exists($parent, $parents)) {
                $errors[] = "#{$number}: parent #{$parent} does not exist.";
            }
        }

        foreach (array_keys($parents) as $number) {
            $seen = [$number => true];
            $cursor = $number;

            while (isset($parents[$cursor])) {
                $cursor = $parents[$cursor];

                if (isset($seen[$cursor])) {
                    $errors[] = "#{$number}: parent chain loops back on itself.";

                    break;
                }

                $seen[$cursor] = true;
            }
        }

        return $errors;
    }
}
