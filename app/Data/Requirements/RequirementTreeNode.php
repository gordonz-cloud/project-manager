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
        $own = DeliveryStatus::of($requirement);
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

        if ($requirement->status === RequirementStatus::Void && $requirement->supersededBy !== null) {
            return RequirementProgress::Superseded;
        }

        if ($children === [] || in_array($requirement->status, [RequirementStatus::Void, RequirementStatus::Later], true)) {
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
     * The same tree cut down to the nodes in $keep and their ancestors; each node keeps its own status and rollup.
     *
     * @param  list<self>  $nodes
     * @param  array<int, mixed>  $keep  keyed by requirement id
     * @return list<self>
     */
    public static function keeping(array $nodes, array $keep): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            $children = self::keeping($node->children, $keep);

            if ($children !== [] || isset($keep[$node->requirement->id])) {
                $kept[] = new self($node->requirement, $node->delivery, $children, $node->rollup, $node->progress);
            }
        }

        return $kept;
    }

    /**
     * @param  list<self>  $nodes
     */
    public static function total(array $nodes): RequirementRollup
    {
        return array_reduce($nodes, fn (RequirementRollup $sum, self $node): RequirementRollup => $sum->plus($node->rollup), new RequirementRollup);
    }
}
