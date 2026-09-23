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
        ?int $requestReplyId = null,
    ): Builder {
        $query->forFeature($featureId)->where('request_reply_id', $requestReplyId);

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
            ->where('request_reply_id', $owner->request_reply_id)
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

    public function parentRule(?ImplementationNode $record, ?int $featureId, ?int $requestReplyId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $featureId, $requestReplyId): void {
            if ($value === null) {
                return;
            }

            $parent = ImplementationNode::query()->find((int) $value);

            if ($parent === null || $parent->feature_id !== $featureId || $parent->request_reply_id !== $requestReplyId) {
                $fail('父节点必须属于同一个入口或功能。');

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
