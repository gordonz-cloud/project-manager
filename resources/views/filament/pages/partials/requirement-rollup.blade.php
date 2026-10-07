<span class="inline-flex shrink-0 gap-1.5 text-xs tabular-nums text-slate-500 dark:text-slate-400" data-rollup>
    @foreach (array_filter($rollup->progressCounts()) as $label => $count)
        @if (! $loop->first)<span>·</span>@endif
        <span>{{ $count }} {{ $label }}</span>
    @endforeach
    @if ($failed = $rollup->count(\App\Data\Requirements\DeliveryStatus::Failed))<span class="text-red-600 dark:text-red-400">✗ {{ $failed }} 验证失败</span>@endif
</span>
