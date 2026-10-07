<?php

namespace App\Data\Requirements;

use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Requirement;

/**
 * One requirement with its subtree. Delivery combines its own links with its decided children;
 * proposals, conflicts and void nodes are shown but never pull a parent's delivery.
 */
final readonly class RequirementTreeNode
{
    /**
     * @param  list<self>  $children
     */
    public function __construct(
        public Requirement $requirement,
        public DeliveryStatus $delivery,
        public array $children,
        public RequirementRollup $rollup,
        public RequirementProgress $progress,
    ) {}

    /**
     * @param  list<self>  $children
     */
    public static function build(Requirement $requirement, array $children): self
    {
        $own = DeliveryStatus::fromLinks($requirement->linkedFeatures, $requirement->tests);
        $decidedChildren = array_filter($children, fn (self $child): bool => $child->requirement->status === RequirementStatus::Decided);
        $delivery = DeliveryStatus::combined(array_values(array_filter([$own, ...array_map(fn (self $child): DeliveryStatus => $child->delivery, $decidedChildren)])));
        $rollup = array_reduce($children, fn (RequirementRollup $sum, self $child): RequirementRollup => $sum->plus($child->rollup), RequirementRollup::of($requirement, $delivery, $children === []));

        return new self($requirement, $delivery, $children, $rollup, self::progressOf($requirement, $delivery, $decidedChildren, $rollup));
    }

    /**
     * A 分组 shows what its rules add up to, never its own decision status: open decisions inside it while none of its
     * rules is decided yet, otherwise how far its decided rules are built.
     *
     * @param  array<int, self>  $decidedChildren
     */
    private static function progressOf(Requirement $requirement, DeliveryStatus $delivery, array $decidedChildren, RequirementRollup $rollup): RequirementProgress
    {
        if ($requirement->kind !== RequirementKind::Group) {
            return RequirementProgress::of($requirement->status, $delivery);
        }

        return $decidedChildren === [] && $rollup->pending() > 0 ? RequirementProgress::Pending : RequirementProgress::of(RequirementStatus::Decided, $delivery);
    }

    /**
     * Every node, depth first in display order.
     *
     * @param  list<self>  $nodes
     * @return list<self>
     */
    public static function flattened(array $nodes): array
    {
        return array_merge(...array_map(fn (self $node): array => [$node, ...self::flattened($node->children)], $nodes));
    }

    /**
     * @param  list<self>  $nodes
     */
    public static function total(array $nodes): RequirementRollup
    {
        return array_reduce($nodes, fn (RequirementRollup $sum, self $node): RequirementRollup => $sum->plus($node->rollup), new RequirementRollup);
    }
}
