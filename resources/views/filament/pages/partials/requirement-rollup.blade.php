{{-- Counts under a node. Needs an @container ancestor: when it is narrower than 36rem only the numbers show, the words move to the tooltip. --}}
@php($counts = array_filter($rollup->progressCounts()))
<span class="inline-flex shrink-0 gap-1.5 whitespace-nowrap text-xs tabular-nums text-slate-500 dark:text-slate-400" title="{{ collect($counts)->map(fn ($count, $label) => "{$count} {$label}")->implode(' · ') }}" data-rollup>
    @foreach ($counts as $label => $count)
        @if (! $loop->first)<span>·</span>@endif
        <span>{{ $count }}<span class="hidden @xl:inline"> {{ $label }}</span></span>
    @endforeach
    @if ($failed = $rollup->count(\App\Data\Requirements\DeliveryStatus::Failed))<span class="text-red-600 dark:text-red-400">✗ {{ $failed }}<span class="hidden @xl:inline"> 验证失败</span></span>@endif
</span>
