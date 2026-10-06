<?php

namespace App\Data\Requirements;

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

    public static function of(Requirement $requirement, DeliveryStatus $delivery, bool $isLeaf): self
    {
        return new self(
            delivered: $isLeaf && $requirement->status === RequirementStatus::Decided ? [$delivery->value => 1] : [],
            proposed: (int) ($requirement->status === RequirementStatus::Proposed),
            conflicts: (int) ($requirement->status === RequirementStatus::Conflict),
        );
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

    public function verifiedPercent(): ?int
    {
        return $this->decided() === 0 ? null : (int) round(100 * $this->count(DeliveryStatus::Verified) / $this->decided());
    }
}
