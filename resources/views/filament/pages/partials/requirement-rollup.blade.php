@php($percent = $rollup->verifiedPercent())
<span class="inline-flex shrink-0 gap-1.5 text-xs tabular-nums">
    @if ($percent !== null)<span class="text-emerald-600 dark:text-emerald-400" title="已定规则里已验证的比例（{{ $rollup->count(\App\Data\Requirements\DeliveryStatus::Verified) }}/{{ $rollup->decided() }}）">{{ $percent }}% 已验证</span>@endif
    @if ($failed = $rollup->count(\App\Data\Requirements\DeliveryStatus::Failed))<span class="text-red-600 dark:text-red-400">✗ {{ $failed }} 验证失败</span>@endif
    @if ($rollup->conflicts)<span class="text-amber-600 dark:text-amber-400">⚠ {{ $rollup->conflicts }} 冲突</span>@endif
    @if ($rollup->proposed)<span class="text-sky-600 dark:text-sky-400">{{ $rollup->proposed }} 提议</span>@endif
</span>
