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

        return new self($requirement, $delivery, $children, $rollup, self::progressOf($requirement, $delivery, $children, $rollup));
    }

    /**
     * A leaf shows its own status. A parent shows what everything under it adds up to (RequirementRollup::progress,
     * open decisions included), so a 完成 never hides a rule still waiting on a decision; a 分组's own decision status
     * never counts.
     *
     * @param  list<self>  $children
     */
    private static function progressOf(Requirement $requirement, DeliveryStatus $delivery, array $children, RequirementRollup $rollup): RequirementProgress
    {
        $status = $requirement->kind === RequirementKind::Group ? RequirementStatus::Decided : $requirement->status;

        if ($children === [] || $requirement->status === RequirementStatus::Void) {
            return RequirementProgress::of($requirement->status, $delivery);
        }

        return $rollup->progress() ?? RequirementProgress::of($status, $delivery);
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
