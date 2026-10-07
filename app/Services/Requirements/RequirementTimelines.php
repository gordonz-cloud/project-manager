<?php

namespace App\Services\Requirements;

use App\Data\Requirements\RequirementTimeline;
use App\Data\Requirements\TimelineEntry;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\RequirementRevision;

/**
 * The 来龙去脉 of a requirement: the timeline written for it, or, when none was written, one pieced together from what
 * is recorded — the rules it replaced (or that replaced it), the dated decisions and documents in its source, and the
 * moments it became 已定. Rewordings are left out (they are our edits, not anyone's new saying), and dates are never
 * made up: a saying without a recorded date is left out.
 */
class RequirementTimelines
{
    public function for(Requirement $requirement): RequirementTimeline
    {
        if (filled($requirement->timeline)) {
            return RequirementTimeline::fromArray($requirement->timeline);
        }

        $own = [...$this->fromSource($requirement), ...$this->fromConfirmations($requirement)];

        if ($requirement->status === RequirementStatus::Decided) {
            $own = $this->withCurrentMarked($requirement, $own);
        }

        return RequirementTimeline::sorted($this->unique([...$own, ...$this->fromReplacements($requirement)]));
    }

    /**
     * Dated pieces of the source: "老板拍板 2026-09-02…", "Gordon 拍板 2026-10-07：选 A …", "老板文档 … 2026-10-06".
     *
     * @return list<TimelineEntry>
     */
    private function fromSource(Requirement $requirement): array
    {
        $entries = [];

        foreach (preg_split('/[；;]/u', (string) $requirement->source) ?: [] as $piece) {
            $who = match (true) {
                str_contains($piece, '老板拍板') => '老板拍板',
                str_contains($piece, 'Gordon 拍板') => 'Gordon 拍板',
                str_contains($piece, '老板文档') => '老板文档',
                default => null,
            };

            if ($who === null || ! preg_match('/\d{4}-\d{2}-\d{2}/', $piece, $date)) {
                continue;
            }

            $picked = str_contains($piece, '：') ? trim((string) mb_substr($piece, mb_strpos($piece, '：') + 1)) : '';
            $entries[] = new TimelineEntry(date: $date[0], who: $who, said: $who === '老板文档' || $picked === '' ? $requirement->title : $picked);
        }

        return $entries;
    }

    /**
     * Each time an open 提议/冲突 was decided, from the revisions (a node created as 已定 is covered by its decided_at).
     *
     * @return list<TimelineEntry>
     */
    private function fromConfirmations(Requirement $requirement): array
    {
        return array_values($requirement->revisions
            ->filter(fn (RequirementRevision $revision): bool => $revision->new_status === RequirementStatus::Decided->value && in_array($revision->old_status, [RequirementStatus::Proposed->value, RequirementStatus::Conflict->value], true) && $revision->created_at !== null)
            ->map(fn (RequirementRevision $revision): TimelineEntry => new TimelineEntry(
                date: $revision->created_at?->toDateString(),
                who: $this->whoDecided($revision->decided_by),
                said: (string) $revision->new_statement,
            ))
            ->all());
    }

    /**
     * The rules this one replaces (down the supersedes chain) and the rule that replaced it.
     *
     * @return list<TimelineEntry>
     */
    private function fromReplacements(Requirement $requirement): array
    {
        $entries = [];
        $seen = [$requirement->id => true];

        for ($old = $requirement->supersedes; $old !== null && ! isset($seen[$old->id]); $old = $old->supersedes) {
            $seen[$old->id] = true;

            if ($old->decided_at !== null) {
                $entries[] = new TimelineEntry(
                    date: $old->decided_at->toDateString(),
                    who: $old->status === RequirementStatus::Void ? '已被取代的旧规则' : $this->whoDecided($old->decided_by),
                    said: $old->title,
                    current: $old->status === RequirementStatus::Decided && $requirement->status !== RequirementStatus::Decided,
                );
            }
        }

        $replacement = $requirement->status === RequirementStatus::Void ? $requirement->supersededBy : null;

        if ($replacement?->decided_at !== null) {
            $entries[] = new TimelineEntry(date: $replacement->decided_at->toDateString(), who: $this->whoDecided($replacement->decided_by), said: $replacement->title, current: true);
        }

        return $entries;
    }

    /**
     * A decided rule's newest saying is the one in force; with none recorded, its decision date stands for it.
     *
     * @param  list<TimelineEntry>  $entries
     * @return list<TimelineEntry>
     */
    private function withCurrentMarked(Requirement $requirement, array $entries): array
    {
        if ($entries === []) {
            return $requirement->decided_at === null ? [] : [new TimelineEntry(date: $requirement->decided_at->toDateString(), who: $this->whoDecided($requirement->decided_by), said: $requirement->title, current: true)];
        }

        $newest = RequirementTimeline::sorted($entries)->entries[0];

        return array_map(fn (TimelineEntry $entry): TimelineEntry => $entry === $newest
            ? new TimelineEntry($entry->date, $entry->who, $entry->said, $entry->where, true, $entry->conflictWith, $entry->related)
            : $entry, $entries);
    }

    /**
     * @param  list<TimelineEntry>  $entries
     * @return list<TimelineEntry>
     */
    private function unique(array $entries): array
    {
        $unique = [];

        foreach ($entries as $entry) {
            $unique["{$entry->date}|{$entry->who}|{$entry->said}"] ??= $entry;
        }

        return array_values($unique);
    }

    private function whoDecided(?string $decidedBy): string
    {
        return match ($decidedBy) {
            '老板' => '老板拍板',
            'Gordon' => 'Gordon 拍板',
            default => '其他',
        };
    }
}
