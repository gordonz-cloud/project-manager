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
            RequirementProgress::Todo->value => $this->count(DeliveryStatus::NotBuilt),
            RequirementProgress::InProgress->value => $this->count(DeliveryStatus::InProgress) + $this->count(DeliveryStatus::Failed),
            RequirementProgress::Done->value => $this->count(DeliveryStatus::Built) + $this->count(DeliveryStatus::Verified),
        ];
    }

    /**
     * One status for a whole subtree: all built → 完成; some built, some not → 进行中; nothing built → 进行中 if any is
     * being built (or failed), 待做 if all are ready to build, otherwise 待决策. Null when the subtree counts nothing.
     */
    public function progress(): ?RequirementProgress
    {
        $done = $this->count(DeliveryStatus::Built) + $this->count(DeliveryStatus::Verified);
        $building = $this->count(DeliveryStatus::InProgress) + $this->count(DeliveryStatus::Failed);
        $todo = $this->count(DeliveryStatus::NotBuilt);
        $total = $done + $building + $todo + $this->pending();

        return match (true) {
            $total === 0 => null,
            $done === $total => RequirementProgress::Done,
            $done > 0, $building > 0 => RequirementProgress::InProgress,
            $todo === $total => RequirementProgress::Todo,
            default => RequirementProgress::Pending,
        };
    }

    public function verifiedPercent(): ?int
    {
        return $this->decided() === 0 ? null : (int) round(100 * $this->count(DeliveryStatus::Verified) / $this->decided());
    }
}
