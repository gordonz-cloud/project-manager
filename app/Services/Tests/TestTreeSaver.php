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
use App\Support\NumberedTreePayload;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves Test Matrix nodes. A node without "number" is new and gets the next number inside the transaction
 * (the project row is locked first, so parallel saves queue instead of reusing a number); a node with
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
            // Parallel saves to one project queue here, so two of them never hand out the same new number.
            Project::query()->whereKey($project->id)->lockForUpdate()->first();
            $existing = Test::withoutGlobalScopes()->where('project_id', $project->id)->get()->keyBy('number');
            $featureIds = Feature::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number');
            /** @var list<TestNodeInput> $nodes */
            [$nodes, $assigned] = NumberedTreePayload::numbered($rawNodes, array_values($existing->map(fn (Test $test): int => $test->number)->all()), 'test');

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

        $errors = [...$errors, ...NumberedTreePayload::parentErrors($parents)];

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
