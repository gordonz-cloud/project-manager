<ul class="mt-2 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
    @foreach ($revisions as $revision)
        <li wire:key="rev-{{ $revision->id }}">
            <span class="text-slate-400">{{ $revision->created_at?->toDateString() }}</span>
            @if ($revision->old_status !== $revision->new_status)
                <span class="font-medium">{{ $revision->old_status ?? '新建' }} → {{ $revision->new_status }}</span>
            @endif
            @if ($revision->changedStatement())
                <span>改写：<span class="line-through opacity-60">{{ $revision->old_statement }}</span> → {{ $revision->new_statement }}</span>
            @endif
            @if ($revision->reason)<span>· {{ $revision->reason }}</span>@endif
            @if ($revision->source || $revision->decided_by)<span class="text-slate-400">· {{ collect([$revision->decided_by, $revision->source])->filter()->implode('，') }}</span>@endif
        </li>
    @endforeach
    @foreach ($commits as $commit)
        <li wire:key="commit-{{ $commit->id }}-{{ $commit->requirement_id ?? '' }}" class="font-mono">
            <span class="text-slate-400">{{ $commit->committed_at->toDateString() }}</span>
            {{ substr($commit->hash, 0, 7) }}
            <span class="font-sans">{{ \Illuminate\Support\Str::limit($commit->subject, 80) }}</span>
            @if ($commit->feature)<span class="font-sans text-slate-400">· F{{ $commit->feature->number }}</span>@endif
        </li>
    @endforeach
    @if ($revisions->isEmpty() && $commits->isEmpty())
        <li class="text-slate-400">没有记录。</li>
    @endif
</ul>
