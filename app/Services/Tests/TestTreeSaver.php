<?php

namespace App\Services\Tests;

use App\Data\Tests\TestTreeSaveResult;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestPriority;
use App\Enums\TestStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves Test Matrix nodes. A node without "number" is new and gets the next number inside the transaction
 * (sqlite runs IMMEDIATE transactions, so parallel saves queue instead of reusing a number); a node with
 * "number" updates that existing node. "ref"/"parent_ref" let new nodes in one payload point at each other.
 * Nodes left out of the payload stay as they are, so a run can send back only the results it changed.
 *
 * @phpstan-type RawNodeInput array{number?: int, ref?: string, parent_ref?: string, parent?: int|null, module?: string|null, action?: string, expected?: string|null, priority?: string|null, platform?: string|null, test_file?: string|null, test_name?: string|null, auto?: string|null, result?: string|null, notes?: string|null, features?: list<int>}
 * @phpstan-type TestNodeInput array{number: int, parent?: int|null, module?: string|null, action?: string, expected?: string|null, priority?: string|null, platform?: string|null, test_file?: string|null, test_name?: string|null, auto?: string|null, result?: string|null, notes?: string|null, features?: list<int>}
 */
class TestTreeSaver
{
    /**
     * @param  array<mixed>  $input  untrusted JSON nodes
     */
    public function save(Project $project, array $input): TestTreeSaveResult
    {
        $rawNodes = $this->parsed($input);

        return DB::transaction(function () use ($project, $rawNodes): TestTreeSaveResult {
            $existing = Test::withoutGlobalScopes()->where('project_id', $project->id)->get()->keyBy('number');
            $featureIds = Feature::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number');
            [$nodes, $assigned] = $this->numbered($rawNodes, array_values($existing->map(fn (Test $test): int => $test->number)->all()));

            $numberById = $existing->pluck('number', 'id');
            $storedParents = $existing->map(fn (Test $test): ?int => $numberById[$test->parent_id] ?? null)->all();

            $this->assertValidTree($nodes, $storedParents, $featureIds->all());

            $saved = [];

            // Parents are cleared first and set in a second pass, so a re-parented subtree never looks like a cycle mid-save.
            foreach ($nodes as $node) {
                $test = $existing->get($node['number']) ?? new Test(['number' => $node['number'], 'status' => TestStatus::Valid, 'last_result' => TestLastResult::NotRun]);
                $test->project_id = $project->id;
                $test->fill($this->columns($node));

                if (array_key_exists('parent', $node)) {
                    $test->parent_id = null;
                }

                $test->save();
                $saved[$node['number']] = $test;
            }

            $numberToId = Test::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number');
            $featureSyncs = 0;

            foreach ($nodes as $node) {
                $test = $saved[$node['number']];

                if (isset($node['parent'])) {
                    $test->update(['parent_id' => $numberToId[$node['parent']]]);
                }

                if (array_key_exists('features', $node)) {
                    $test->features()->sync(array_map(fn (int $number): int => $featureIds[$number], $node['features']));
                    $featureSyncs++;
                }
            }

            return new TestTreeSaveResult($assigned, count($nodes) - count($assigned), $featureSyncs);
        });
    }

    /**
     * @param  array<mixed>  $input
     * @return list<RawNodeInput>
     */
    private function parsed(array $input): array
    {
        $strings = ['module', 'action', 'expected', 'priority', 'platform', 'test_file', 'test_name', 'auto', 'result', 'notes'];
        $nodes = [];

        foreach ($input as $index => $node) {
            $valid = is_array($node)
                && (! isset($node['number']) || is_int($node['number']))
                && (! isset($node['ref']) || is_string($node['ref']))
                && (! isset($node['parent_ref']) || (is_string($node['parent_ref']) && ! isset($node['parent'])))
                && (! isset($node['parent']) || is_int($node['parent']))
                && (! isset($node['features']) || (is_array($node['features']) && array_is_list($node['features']) && array_filter($node['features'], 'is_int') === $node['features']))
                && array_filter($strings, fn (string $key): bool => isset($node[$key]) && ! is_string($node[$key])) === [];

            if (! $valid) {
                throw new InvalidArgumentException("Node at index {$index}: number must be an integer, ref/parent_ref strings (parent_ref not with parent), parent an integer or null, features a list of integers, text fields strings.");
            }

            /** @var RawNodeInput $node */
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Gives new nodes the next free numbers and turns parent_ref into parent; unknown numbers or refs reject the payload.
     *
     * @param  list<RawNodeInput>  $rawNodes
     * @param  list<int>  $existingNumbers
     * @return array{list<TestNodeInput>, array<string, int>} nodes, and ref (or "row N") => assigned number
     */
    private function numbered(array $rawNodes, array $existingNumbers): array
    {
        $next = ($existingNumbers === [] ? 0 : max($existingNumbers)) + 1;
        $known = array_flip($existingNumbers);
        $numberByRef = [];
        $assigned = [];
        $errors = [];

        foreach ($rawNodes as $index => $node) {
            if (isset($node['number']) && ! isset($known[$node['number']])) {
                $errors[] = "#{$node['number']}: no such test; omit number to create a new node.";
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
            /** @var TestNodeInput $node */
            $nodes[] = $node;
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return [$nodes, $assigned];
    }

    /**
     * @param  TestNodeInput  $node
     * @return array<string, mixed>
     */
    private function columns(array $node): array
    {
        $map = [
            'module' => 'module', 'action' => 'title', 'expected' => 'expected', 'priority' => 'priority',
            'platform' => 'platform', 'test_file' => 'location', 'test_name' => 'test_name', 'auto' => 'auto',
            'result' => 'last_result', 'notes' => 'notes',
        ];
        $columns = [];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $node)) {
                $columns[$column] = $node[$key] === '' ? null : $node[$key];
            }
        }

        return $columns;
    }

    /**
     * @param  list<TestNodeInput>  $nodes
     * @param  array<int, int|null>  $storedParents  number => parent number
     * @param  array<int, int>  $featureIds  number => id
     */
    private function assertValidTree(array $nodes, array $storedParents, array $featureIds): void
    {
        $errors = [];
        $parents = $storedParents;

        foreach ($nodes as $node) {
            $number = $node['number'];

            if (! array_key_exists($number, $storedParents) && blank($node['action'] ?? null)) {
                $errors[] = "#{$number}: a new node needs an action.";
            }

            $errors = [...$errors, ...$this->enumErrors($number, $node)];

            foreach ($node['features'] ?? [] as $featureNumber) {
                if (! isset($featureIds[$featureNumber])) {
                    $errors[] = "#{$number}: feature {$featureNumber} does not exist in this project.";
                }
            }

            $parents[$number] = array_key_exists('parent', $node) ? $node['parent'] : ($storedParents[$number] ?? null);
        }

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

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", array_unique($errors)));
        }
    }

    /**
     * @param  TestNodeInput  $node
     * @return list<string>
     */
    private function enumErrors(int $number, array $node): array
    {
        $enums = ['priority' => TestPriority::class, 'auto' => TestAuto::class, 'result' => TestLastResult::class];
        $errors = [];

        foreach ($enums as $key => $enum) {
            $value = $node[$key] ?? null;

            if (filled($value) && $enum::tryFrom($value) === null) {
                $errors[] = "#{$number}: {$key} \"{$value}\" is not one of ".implode('/', array_column($enum::cases(), 'value')).'.';
            }
        }

        return $errors;
    }
}
