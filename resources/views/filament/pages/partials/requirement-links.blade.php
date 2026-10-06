<div class="flex flex-wrap gap-1">
    @forelse ($features as $feature)
        <a href="{{ $this->featureUrl($feature) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="{{ $feature->title }}">F{{ $feature->number }} {{ \Illuminate\Support\Str::limit($feature->title, 24) }} · {{ $feature->status->value }}</a>
    @empty
        <span class="text-xs text-slate-400">没有功能</span>
    @endforelse
</div>
<div class="mt-1 flex flex-wrap gap-1">
    @forelse ($tests as $test)
        <a href="{{ $this->testUrl($test) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="{{ $test->title }}">T{{ $test->number }} · {{ $test->last_result->value }}</a>
    @empty
        <span class="text-xs text-slate-400">没有测试节点</span>
    @endforelse
</div>
