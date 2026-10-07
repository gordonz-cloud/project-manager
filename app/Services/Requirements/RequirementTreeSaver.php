<?php

namespace App\Services\Requirements;

use App\Data\Requirements\RequirementTreeSaveResult;
use App\Enums\RequirementDecider;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Support\NumberedTreePayload;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves requirement-tree nodes the way tests:save saves test nodes: no "number" = new (numbered inside the
 * transaction), "number" = update, "ref"/"parent_ref" link new nodes. On top of that it guards the decision record:
 * levels must nest (目标 → 子目标 → 规则), a proposal that supersedes a 已定 rule is a 冲突, changing a 已定 rule
 * needs a reason, and deciding a 冲突 voids the rule it replaces. Revisions are written by Requirement itself.
 *
 * @phpstan-type NodeInput array{number: int, parent?: int|null, supersedes?: int|null, kind?: string, title?: string, rationale?: string|null, source?: string|null, status?: string, decided_by?: string|null, decided_at?: string|null, decider?: string|null, reason?: string|null, features?: list<int>, tests?: list<int>}
 * @phpstan-type NodeState array{kind: RequirementKind|null, status: RequirementStatus|null, parent: int|null, supersedes: int|null}
 */
class RequirementTreeSaver
{
    private const TEXT_FIELDS = ['kind', 'title', 'rationale', 'source', 'status', 'decided_by', 'decided_at', 'decider', 'reason'];

    /**
     * @param  array<mixed>  $input  untrusted JSON nodes
     */
    public function save(Project $project, array $input): RequirementTreeSaveResult
    {
        $rawNodes = $this->parsed($input);

        return DB::transaction(function () use ($project, $rawNodes): RequirementTreeSaveResult {
            $existing = Requirement::withoutGlobalScopes()->where('project_id', $project->id)->get()->keyBy('number');
            $featureIds = Feature::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number')->all();
            $testIds = Test::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number')->all();

            /** @var list<NodeInput> $nodes */
            [$nodes, $assigned] = NumberedTreePayload::numbered($rawNodes, array_values($existing->map(fn (Requirement $requirement): int => (int) $requirement->number)->all()), 'requirement');
            $nodes = array_map(fn (array $node): array => $this->withConflictMarked($node, $existing->get($node['number'])), $nodes);

            $this->assertValid($nodes, $existing->all(), $featureIds, $testIds);

            $saved = [];

            foreach ($nodes as $node) {
                $requirement = $existing->get($node['number']) ?? new Requirement(['number' => $node['number'], 'status' => RequirementStatus::Proposed]);
                $requirement->project_id = $project->id;
                $requirement->fill($this->columns($node));
                $requirement->revisionReason = $node['reason'] ?? null;

                if (array_key_exists('parent', $node)) {
                    $requirement->parent_id = null;
                }

                $requirement->save();
                $saved[$node['number']] = $requirement;
            }

            $idByNumber = Requirement::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id', 'number');
            $voided = [];

            foreach ($nodes as $node) {
                $requirement = $saved[$node['number']];

                if (array_key_exists('parent', $node) || array_key_exists('supersedes', $node)) {
                    $requirement->update([
                        'parent_id' => isset($node['parent']) ? $idByNumber[$node['parent']] : $requirement->parent_id,
                        'supersedes_id' => array_key_exists('supersedes', $node) ? ($node['supersedes'] === null ? null : $idByNumber[$node['supersedes']]) : $requirement->supersedes_id,
                    ]);
                }

                if (array_key_exists('features', $node)) {
                    $requirement->linkedFeatures()->sync(array_map(fn (int $number): int => $featureIds[$number], $node['features']));
                }

                if (array_key_exists('tests', $node)) {
                    $requirement->tests()->sync(array_map(fn (int $number): int => $testIds[$number], $node['tests']));
                }

                if ($this->voidSuperseded($requirement, $node['reason'] ?? '')) {
                    $voided[] = (int) $requirement->supersedes?->number;
                }
            }

            return new RequirementTreeSaveResult($assigned, count($nodes) - count($assigned), $voided);
        });
    }

    /**
     * A decided replacement retires the rule it supersedes.
     */
    private function voidSuperseded(Requirement $requirement, string $reason): bool
    {
        $superseded = $requirement->supersedes;

        if ($requirement->status !== RequirementStatus::Decided || $superseded === null || $superseded->status === RequirementStatus::Void) {
            return false;
        }

        $superseded->status = RequirementStatus::Void;
        $superseded->revisionReason = "被 #{$requirement->number} 取代".($requirement->source ? "（{$requirement->source}）" : '').'：'.$reason;
        $superseded->save();

        return true;
    }

    /**
     * A proposal pointing at a rule it would replace is a conflict.
     *
     * @param  NodeInput  $node
     * @return NodeInput
     */
    private function withConflictMarked(array $node, ?Requirement $stored): array
    {
        $supersedes = array_key_exists('supersedes', $node) ? $node['supersedes'] : $stored?->supersedes_id;
        $status = $node['status'] ?? $stored?->status->value ?? RequirementStatus::Proposed->value;

        if ($supersedes !== null && $status === RequirementStatus::Proposed->value) {
            $node['status'] = RequirementStatus::Conflict->value;
        }

        return $node;
    }

    /**
     * @param  array<mixed>  $input
     * @return list<array{number?: int, ref?: string, parent_ref?: string, parent?: int|null}>
     */
    private function parsed(array $input): array
    {
        $nodes = [];

        foreach ($input as $index => $node) {
            $valid = is_array($node)
                && (! isset($node['number']) || is_int($node['number']))
                && (! isset($node['ref']) || is_string($node['ref']))
                && (! isset($node['parent_ref']) || (is_string($node['parent_ref']) && ! isset($node['parent'])))
                && (! isset($node['parent']) || is_int($node['parent']))
                && (! isset($node['supersedes']) || is_int($node['supersedes']))
                && $this->isNumberList($node['features'] ?? [])
                && $this->isNumberList($node['tests'] ?? [])
                && array_filter(self::TEXT_FIELDS, fn (string $key): bool => isset($node[$key]) && ! is_string($node[$key])) === [];

            if (! $valid) {
                throw new InvalidArgumentException("Node at index {$index}: number/parent/supersedes must be integers, ref/parent_ref strings (parent_ref not with parent), features/tests lists of integers, text fields strings.");
            }

            /** @var array{number?: int, ref?: string, parent_ref?: string, parent?: int|null} $node */
            $nodes[] = $node;
        }

        return $nodes;
    }

    private function isNumberList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, 'is_int') === $value;
    }

    /**
     * @param  NodeInput  $node
     * @return array<string, mixed>
     */
    private function columns(array $node): array
    {
        $columns = [];

        foreach (['kind', 'title', 'rationale', 'source', 'status', 'decided_by', 'decided_at', 'decider'] as $key) {
            if (array_key_exists($key, $node)) {
                $columns[$key] = $node[$key] === '' ? null : $node[$key];
            }
        }

        return $columns;
    }

    /**
     * @param  list<NodeInput>  $nodes
     * @param  array<int, Requirement>  $existing  number => stored node
     * @param  array<int, int>  $featureIds  number => id
     * @param  array<int, int>  $testIds  number => id
     */
    private function assertValid(array $nodes, array $existing, array $featureIds, array $testIds): void
    {
        $errors = [];
        $numberById = collect($existing)->mapWithKeys(fn (Requirement $requirement): array => [$requirement->id => (int) $requirement->number])->all();
        $states = array_map(fn (Requirement $requirement): array => [
            'kind' => $requirement->kind,
            'status' => $requirement->status,
            'parent' => $numberById[$requirement->parent_id] ?? null,
            'supersedes' => $numberById[$requirement->supersedes_id] ?? null,
        ], $existing);

        foreach ($nodes as $node) {
            $number = $node['number'];
            $stored = $existing[$number] ?? null;
            $errors = [...$errors, ...$this->fieldErrors($number, $node, $stored, $featureIds, $testIds)];

            $states[$number] = [
                'kind' => array_key_exists('kind', $node) ? RequirementKind::tryFrom($node['kind']) : $stored?->kind,
                'status' => array_key_exists('status', $node) ? RequirementStatus::tryFrom($node['status']) : ($stored->status ?? RequirementStatus::Proposed),
                'parent' => array_key_exists('parent', $node) ? $node['parent'] : ($states[$number]['parent'] ?? null),
                'supersedes' => array_key_exists('supersedes', $node) ? $node['supersedes'] : ($states[$number]['supersedes'] ?? null),
            ];
        }

        $errors = [...$errors, ...NumberedTreePayload::parentErrors(array_map(fn (array $state): ?int => $state['parent'], $states))];

        foreach ($nodes as $node) {
            $errors = [...$errors, ...$this->levelErrors($node['number'], $states), ...$this->supersedeErrors($node, $states)];
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", array_unique($errors)));
        }
    }

    /**
     * @param  NodeInput  $node
     * @param  array<int, int>  $featureIds
     * @param  array<int, int>  $testIds
     * @return list<string>
     */
    private function fieldErrors(int $number, array $node, ?Requirement $stored, array $featureIds, array $testIds): array
    {
        $errors = [];

        if ($stored === null && (blank($node['title'] ?? null) || blank($node['kind'] ?? null))) {
            $errors[] = "#{$number}: a new node needs a title and a kind.";
        }

        if (filled($node['kind'] ?? null) && RequirementKind::tryFrom($node['kind']) === null) {
            $errors[] = "#{$number}: kind \"{$node['kind']}\" is not one of ".implode('/', array_column(RequirementKind::cases(), 'value')).'.';
        }

        if (isset($node['status']) && RequirementStatus::tryFrom($node['status']) === null) {
            $errors[] = "#{$number}: status \"{$node['status']}\" is not one of ".implode('/', array_column(RequirementStatus::cases(), 'value')).'.';
        }

        if (filled($node['decided_at'] ?? null) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $node['decided_at'])) {
            $errors[] = "#{$number}: decided_at must be YYYY-MM-DD.";
        }

        if (filled($node['decider'] ?? null)) {
            $status = RequirementStatus::tryFrom($node['status'] ?? '') ?? $stored->status ?? RequirementStatus::Proposed;

            if (RequirementDecider::tryFrom($node['decider']) === null) {
                $errors[] = "#{$number}: decider \"{$node['decider']}\" is not one of ".implode('/', array_column(RequirementDecider::cases(), 'value')).'.';
            } elseif (! $status->awaitsDecision()) {
                $errors[] = "#{$number}: decider only applies to a 提议 or 冲突, this node is {$status->value}.";
            }
        }

        foreach ($node['features'] ?? [] as $featureNumber) {
            if (! isset($featureIds[$featureNumber])) {
                $errors[] = "#{$number}: feature {$featureNumber} does not exist in this project.";
            }
        }

        foreach ($node['tests'] ?? [] as $testNumber) {
            if (! isset($testIds[$testNumber])) {
                $errors[] = "#{$number}: test {$testNumber} does not exist in this project.";
            }
        }

        $changesDecidedRule = $stored?->status === RequirementStatus::Decided
            && ((isset($node['title']) && $node['title'] !== $stored->title) || (isset($node['status']) && $node['status'] !== $stored->status->value));
        $decidesReplacement = ($node['status'] ?? null) === RequirementStatus::Decided->value
            && $stored?->status !== RequirementStatus::Decided
            && ($node['supersedes'] ?? $stored?->supersedes_id) !== null;

        if (($changesDecidedRule || $decidesReplacement) && blank($node['reason'] ?? null)) {
            $errors[] = "#{$number}: changing a 已定 rule needs a reason.";
        }

        return $errors;
    }

    /**
     * @param  array<int, NodeState>  $states
     * @return list<string>
     */
    private function levelErrors(int $number, array $states): array
    {
        $kind = $states[$number]['kind'];
        $parent = $states[$number]['parent'];

        if ($kind === null) {
            return [];
        }

        $allowed = $kind->allowedParents();

        if ($allowed === []) {
            return $parent === null ? [] : ["#{$number}: a {$kind->value} must be a root."];
        }

        $parentKind = $parent === null ? null : ($states[$parent]['kind'] ?? null);

        return in_array($parentKind, $allowed, true) ? [] : [
            "#{$number}: a {$kind->value} must sit under ".implode(' or ', array_map(fn (RequirementKind $allowedKind): string => $allowedKind->value, $allowed)).'.',
        ];
    }

    /**
     * @param  NodeInput  $node
     * @param  array<int, NodeState>  $states
     * @return list<string>
     */
    private function supersedeErrors(array $node, array $states): array
    {
        $number = $node['number'];
        $state = $states[$number];
        $target = $state['supersedes'];

        if ($state['status'] === RequirementStatus::Conflict && $target === null) {
            return ["#{$number}: a 冲突 must name the rule it supersedes."];
        }

        if (! array_key_exists('supersedes', $node) || $target === null) {
            return [];
        }

        return match (true) {
            $target === $number => ["#{$number}: a node cannot supersede itself."],
            ! isset($states[$target]) => ["#{$number}: supersedes #{$target}, which does not exist in this project."],
            ! ($state['status']?->awaitsDecision() ?? false) => ["#{$number}: only a 提议 can supersede a rule."],
            $states[$target]['status'] !== RequirementStatus::Decided => ["#{$number}: can only supersede a 已定 rule, #{$target} is {$states[$target]['status']?->value}."],
            default => [],
        };
    }
}
