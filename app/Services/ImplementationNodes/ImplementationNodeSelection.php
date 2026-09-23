<?php

namespace App\Services\ImplementationNodes;

use App\Models\ImplementationNode;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class ImplementationNodeSelection
{
    /**
     * @param  Builder<ImplementationNode>  $query
     * @return Builder<ImplementationNode>
     */
    public function constrainToFeature(Builder $query, mixed $featureId): Builder
    {
        return $query->forFeature($this->normalizeFeatureId($featureId));
    }

    /**
     * @param  Builder<ImplementationNode>  $query
     * @return Builder<ImplementationNode>
     */
    public function constrainToAvailableParent(
        Builder $query,
        ?ImplementationNode $record,
        ?int $featureId,
    ): Builder {
        $query->forFeature($featureId);

        if ($record === null) {
            return $query;
        }

        return $query
            ->whereKeyNot($record->id)
            ->whereNotIn('id', ImplementationNode::descendantIds($record->id));
    }

    /**
     * @param  Builder<ImplementationNode>  $query
     * @return Builder<ImplementationNode>
     */
    public function constrainToAssociableChild(Builder $query, ImplementationNode $owner): Builder
    {
        return $query
            ->forFeature($owner->feature_id)
            ->whereKeyNot($owner->id)
            ->whereNotIn('id', [
                ...ImplementationNode::descendantIds($owner->id),
                ...ImplementationNode::ancestorIds($owner->id),
            ]);
    }

    public function existsInFeatureRule(mixed $featureId): Exists
    {
        return Rule::exists(ImplementationNode::class, 'id')
            ->where('feature_id', $this->normalizeFeatureId($featureId));
    }

    public function parentRule(?ImplementationNode $record, ?int $featureId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $featureId): void {
            if ($value === null) {
                return;
            }

            $parent = ImplementationNode::query()->find((int) $value);

            if ($parent === null || $parent->feature_id !== $featureId) {
                $fail('父节点必须属于同一个功能。');

                return;
            }

            if ($record !== null && ImplementationNode::wouldCycle($record->id, (int) $value)) {
                $fail('父节点不能是当前节点或它的后代。');
            }
        };
    }

    private function normalizeFeatureId(mixed $featureId): ?int
    {
        return blank($featureId) ? null : (int) $featureId;
    }
}
