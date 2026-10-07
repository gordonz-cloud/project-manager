<?php

namespace App\Data\Requirements;

use App\Enums\RequirementStatus;

/**
 * The one status a node shows: where it stands for Gordon, from its decision status and, once 已定, its delivery.
 * Never stored.
 */
enum RequirementProgress: string
{
    case Pending = '待决策';
    case Todo = '待做';
    case InProgress = '进行中';
    case Done = '完成';
    case Dropped = '放弃';

    public static function of(RequirementStatus $status, DeliveryStatus $delivery): self
    {
        return match (true) {
            $status->awaitsDecision() => self::Pending,
            $status === RequirementStatus::Void => self::Dropped,
            $delivery === DeliveryStatus::NotBuilt => self::Todo,
            $delivery === DeliveryStatus::InProgress, $delivery === DeliveryStatus::Failed => self::InProgress,
            default => self::Done,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            self::Todo => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
            self::InProgress => 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
            self::Done => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Dropped => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400',
        };
    }
}
