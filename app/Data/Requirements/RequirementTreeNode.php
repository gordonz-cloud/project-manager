<?php

namespace App\Data\Requirements;

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

        return new self($requirement, $delivery, $children, $rollup);
    }

    /**
     * @param  list<self>  $nodes
     */
    public static function total(array $nodes): RequirementRollup
    {
        return array_reduce($nodes, fn (RequirementRollup $sum, self $node): RequirementRollup => $sum->plus($node->rollup), new RequirementRollup);
    }
}
