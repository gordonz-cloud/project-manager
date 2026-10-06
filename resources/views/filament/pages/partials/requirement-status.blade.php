{{-- Decision status badge for anything not simply 已定. --}}
@php($status = $requirement->status)
@if ($status !== \App\Enums\RequirementStatus::Decided)
    <span class="shrink-0 rounded px-1 text-xs {{ match ($status) {
        \App\Enums\RequirementStatus::Conflict => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
        \App\Enums\RequirementStatus::Proposed => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
        default => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400',
    } }}">{{ $status->value }}</span>
@endif
