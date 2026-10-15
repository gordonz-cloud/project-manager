<?php

namespace App\Data\Requirements;

use App\Models\RequirementRevision;
use Carbon\CarbonInterface;

/**
 * One line of the 最近变化 timeline: what happened to a requirement and when, in one tag.
 */
final readonly class RequirementChange
{
    public const NEW = '新';

    public const VOIDED = '作废';

    public const STATUS = '状态';

    public const REWORDED = '改说法';

    public const ACCEPTED = '验收';

    public const NOTE = '记录';

    /**
     * @param  string  $kind  one of the constants above
     * @param  string|null  $detail  what moved: "已定 → 以后做", or the statement it had before
     */
    public function __construct(
        public string $key,
        public int $requirementId,
        public CarbonInterface $at,
        public string $kind,
        public ?string $detail,
        public ?string $reason,
    ) {}

    /**
     * A revision as one tag, the biggest thing it did winning: new, voided, a status move, a new statement, an acceptance.
     */
    public static function fromRevision(RequirementRevision $revision): self
    {
        $statusMoved = $revision->old_status !== $revision->new_status;

        [$kind, $detail] = match (true) {
            $revision->old_status === null => [self::NEW, null],
            $statusMoved && $revision->new_status === '作废' => [self::VOIDED, "{$revision->old_status} → 作废"],
            $statusMoved => [self::STATUS, "{$revision->old_status} → {$revision->new_status}"],
            $revision->changedStatement() => [self::REWORDED, $revision->old_statement],
            $revision->reason === '验收通过' => [self::ACCEPTED, '待验收 → 完成'],
            default => [self::NOTE, null],
        };

        return new self("rev-{$revision->id}", $revision->requirement_id, $revision->created_at ?? now(), $kind, $detail, $revision->reason);
    }

    /**
     * A requirement older than revisions: its creation stands in for the missing 新 revision.
     */
    public static function created(int $requirementId, CarbonInterface $at): self
    {
        return new self("new-{$requirementId}", $requirementId, $at, self::NEW, null, null);
    }

    public function badgeClasses(): string
    {
        return match ($this->kind) {
            self::NEW => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::VOIDED => 'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-slate-300',
            self::STATUS => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
            self::REWORDED => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            self::ACCEPTED => 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
        };
    }
}
