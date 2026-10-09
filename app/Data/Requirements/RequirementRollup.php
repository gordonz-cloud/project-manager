<?php

namespace App\Data\Requirements;

use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Models\Requirement;

/**
 * A subtree at a glance: decided leaves counted by delivery, open decisions counted on the side.
 */
final readonly class RequirementRollup
{
    /**
     * @param  array<string, int>  $delivered  DeliveryStatus value => decided leaves in that state
     */
    public function __construct(
        public array $delivered = [],
        public int $proposed = 0,
        public int $conflicts = 0,
    ) {}

    /**
     * A 分组 is only a way of filing rules, so its own decision status is never counted as an open decision.
     */
    public static function of(Requirement $requirement, DeliveryStatus $delivery, bool $isLeaf): self
    {
        $isDecision = $requirement->kind !== RequirementKind::Group;

        return new self(
            delivered: $isLeaf && $requirement->status === RequirementStatus::Decided ? [$delivery->value => 1] : [],
            proposed: (int) ($isDecision && $requirement->status === RequirementStatus::Proposed),
            conflicts: (int) ($isDecision && $requirement->status === RequirementStatus::Conflict),
        );
    }

    public function pending(): int
    {
        return $this->proposed + $this->conflicts;
    }

    public function plus(self $other): self
    {
        $delivered = $this->delivered;

        foreach ($other->delivered as $status => $count) {
            $delivered[$status] = ($delivered[$status] ?? 0) + $count;
        }

        return new self($delivered, $this->proposed + $other->proposed, $this->conflicts + $other->conflicts);
    }

    public function count(DeliveryStatus $status): int
    {
        return $this->delivered[$status->value] ?? 0;
    }

    public function decided(): int
    {
        return array_sum($this->delivered);
    }

    /**
     * Counts per RequirementProgress (放弃 left out): open decisions, then decided leaves by how far they are built.
     *
     * @return array<string, int> RequirementProgress value => count
     */
    public function progressCounts(): array
    {
        return [
            RequirementProgress::Pending->value => $this->pending(),
            RequirementProgress::Todo->value => $this->count(DeliveryStatus::NotBuilt) + $this->count(DeliveryStatus::InProgress) + $this->count(DeliveryStatus::Failed),
            RequirementProgress::AwaitingAcceptance->value => $this->count(DeliveryStatus::AwaitingAcceptance),
            RequirementProgress::Done->value => $this->count(DeliveryStatus::Built) + $this->count(DeliveryStatus::Verified),
        ];
    }

    /**
     * One status for a whole subtree: any open decision → 待决策; else anything not built (being built or failing
     * included) → 待做; else anything waiting for Gordon to accept → 待验收; else 完成. Null when the subtree counts nothing.
     */
    public function progress(): ?RequirementProgress
    {
        $counts = $this->progressCounts();

        return match (true) {
            $counts[RequirementProgress::Pending->value] > 0 => RequirementProgress::Pending,
            $counts[RequirementProgress::Todo->value] > 0 => RequirementProgress::Todo,
            $counts[RequirementProgress::AwaitingAcceptance->value] > 0 => RequirementProgress::AwaitingAcceptance,
            $counts[RequirementProgress::Done->value] > 0 => RequirementProgress::Done,
            default => null,
        };
    }

    public function verifiedPercent(): ?int
    {
        return $this->decided() === 0 ? null : (int) round(100 * $this->count(DeliveryStatus::Verified) / $this->decided());
    }
}
