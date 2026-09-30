<span class="inline-flex shrink-0 gap-1.5 text-xs tabular-nums">
    @if ($rollup->passed)<span class="text-emerald-600 dark:text-emerald-400" title="通过">●{{ $rollup->passed }}</span>@endif
    @if ($rollup->failed)<span class="text-red-600 dark:text-red-400" title="失败">✗{{ $rollup->failed }}</span>@endif
    @if ($rollup->blocked)<span class="text-orange-600 dark:text-orange-400" title="被挡">⛔{{ $rollup->blocked }}</span>@endif
    @if ($rollup->manual)<span class="text-slate-500" title="手测">○{{ $rollup->manual }}</span>@endif
    @if ($rollup->fakeGreen)<span class="text-amber-600 dark:text-amber-400" title="假绿">⚠{{ $rollup->fakeGreen }}</span>@endif
</span>
