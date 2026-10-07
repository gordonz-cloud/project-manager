{{-- 来龙去脉: everything said about this requirement, newest first; the code's behaviour last. No numbers, no section marks. --}}
@php($current = $timeline->current())
<section class="mt-4" data-timeline>
    <h3 class="text-xs font-medium text-slate-500">来龙去脉</h3>
    <ol class="mt-2 space-y-2 border-l border-slate-200 pl-3 dark:border-white/10">
        @foreach ($timeline->entries as $entry)
            <li wire:key="timeline-{{ $loop->index }}" class="relative text-sm {{ $entry->related ? 'opacity-70' : '' }}" data-timeline-entry>
                <span class="absolute -left-[17px] top-1.5 size-2 {{ $entry->isCode() ? 'rounded-sm bg-violet-500' : ($entry->current ? 'rounded-full bg-emerald-500' : 'rounded-full bg-slate-300 dark:bg-white/30') }}"></span>
                <p class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span class="tabular-nums">{{ $entry->date ?? '现在' }}</span>
                    <span class="rounded px-1 {{ $entry->badgeClasses() }}">{{ $entry->whoLabel() }}</span>
                    @if ($entry->where)<span>{{ $this->mentions->html($entry->where) }}</span>@endif
                    @if ($entry->current)<span class="rounded bg-emerald-100 px-1 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">现在生效</span>@endif
                    @if ($entry->related)<span>（相关）</span>@endif
                </p>
                <p class="mt-0.5 text-slate-800 dark:text-slate-200">{{ $this->mentions->html($entry->said) }}</p>
                @if ($entry->conflictWith && ! $entry->isCode())
                    <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">⚠ 和「{{ $this->mentions->html($entry->conflictWith) }}」冲突</p>
                @endif
            </li>
        @endforeach
    </ol>
    @if ($timeline->codeDisagrees())
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400" data-code-disagrees>⚠ 代码和现行说法不一致</p>
    @endif
    @if ($requirement->rationale && ! $this->repeatsDecision($requirement))
        <details class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            <summary class="cursor-pointer">当时写的理由</summary>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ $this->mentions->html($requirement->rationale) }}</p>
        </details>
    @endif
    @if ($requirement->source)<p class="mt-1 break-all text-xs text-slate-400">来源：{{ $this->mentions->html($requirement->source) }}</p>@endif
</section>
