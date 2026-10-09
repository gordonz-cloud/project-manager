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
    case AwaitingAcceptance = '待验收';
    case Done = '完成';
    case Dropped = '放弃';
    case Superseded = '已被取代';
    case Later = '以后做';

    public static function of(RequirementStatus $status, DeliveryStatus $delivery): self
    {
        return match (true) {
            $status->awaitsDecision() => self::Pending,
            $status === RequirementStatus::Void => self::Dropped,
            $status === RequirementStatus::Later => self::Later,
            $delivery === DeliveryStatus::AwaitingAcceptance => self::AwaitingAcceptance,
            $delivery === DeliveryStatus::Built, $delivery === DeliveryStatus::Verified => self::Done,
            default => self::Todo,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            self::Todo => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
            self::AwaitingAcceptance => 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
            self::Done => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Dropped, self::Superseded => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400',
            self::Later => 'bg-slate-200 text-slate-700 dark:bg-white/15 dark:text-slate-200',
        };
    }
}
