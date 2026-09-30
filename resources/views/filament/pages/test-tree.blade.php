<x-filament-panels::page>
    @php($test = $this->selectedTest)

    <div class="flex flex-wrap items-center gap-3 text-sm" data-test-summary>
        <span class="text-slate-500 dark:text-slate-400">全项目</span>
        @include('filament.pages.partials.test-rollup', ['rollup' => $this->total])
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <aside class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-white/10">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="搜索..."
                    aria-label="搜索测试树"
                    class="min-h-9 min-w-40 flex-1 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-hidden placeholder:text-slate-400 focus:border-slate-500 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                >
                @foreach (\App\Filament\Pages\TestTree::FILTERS as $key => $label)
                    <button type="button" wire:click="setFilter(@js($key))" class="rounded-md border px-2 py-1 text-xs {{ $this->filter === $key ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-300 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="max-h-[calc(100vh-14rem)] overflow-y-auto p-2">
                @forelse ($this->visibleAreas as $area)
                    @php($areaOpen = $this->isNarrowed() || ($this->expandedAreas[$area->name] ?? false))
                    <div wire:key="area-{{ md5($area->name) }}" data-test-area="{{ $area->name }}">
                        <button type="button" wire:click="toggleArea(@js($area->name))" class="flex min-h-8 w-full items-center gap-2 rounded-md px-1 py-1 text-left text-sm font-medium text-slate-950 hover:bg-slate-50 dark:text-white dark:hover:bg-white/5">
                            <x-filament::icon icon="{{ $areaOpen ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4 shrink-0 text-slate-400" />
                            <span class="min-w-0 flex-1 truncate">{{ $area->name }}</span>
                            @include('filament.pages.partials.test-rollup', ['rollup' => $area->rollup])
                        </button>
                        @if ($areaOpen)
                            <div class="ml-3.5 pl-1">
                                @foreach ($area->nodes as $node)
                                    @include('filament.pages.partials.test-tree-node', ['node' => $node, 'areaKey' => md5($area->name)])
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-2 py-6 text-center text-xs text-slate-400">{{ $this->isNarrowed() ? '没有匹配。' : '还没有测试。' }}</p>
                @endforelse

                <div class="mt-4 border-t border-slate-200 px-2 pt-3 dark:border-white/10" data-uncovered-features>
                    <h3 class="text-xs font-medium text-slate-500 dark:text-slate-400">没有被任何测试覆盖的功能（{{ $this->uncoveredFeatures->count() }}）</h3>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($this->uncoveredFeatures as $feature)
                            <a href="{{ $this->featureUrl($feature) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="{{ $feature->title }}">F{{ $feature->number }} {{ \Illuminate\Support\Str::limit($feature->title, 24) }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </aside>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-white/10 dark:bg-slate-950 xl:max-h-[calc(100vh-10rem)] xl:overflow-y-auto">
            @if ($test)
                @php($state = $this->selectedState())
                <nav class="flex flex-wrap gap-1 text-xs text-slate-500 dark:text-slate-400" data-test-path>
                    @foreach ($this->selectedPath() as $step)
                        @if (! $loop->first)<span>›</span>@endif
                        <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline">{{ \Illuminate\Support\Str::limit($step->title, 30) }}</button>
                    @endforeach
                </nav>
                <h2 class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ $test->title }}</h2>
                <dl class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                    <div><dt class="text-xs text-slate-500">状态</dt><dd data-test-state>{{ $state?->symbol() }} {{ $state?->label() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">预期</dt><dd class="whitespace-pre-wrap" data-test-expected>{{ $test->expected ?: '—' }}</dd></div>
                    <div class="flex gap-6">
                        <div><dt class="text-xs text-slate-500">优先级</dt><dd>{{ $test->priority?->value ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">auto</dt><dd>{{ $test->auto?->value ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">业务区</dt><dd>{{ $test->area() }}</dd></div>
                    </div>
                    <div><dt class="text-xs text-slate-500">测试证据</dt><dd class="break-all font-mono text-xs">{{ $test->location ?: '缺测试' }}@if ($test->test_name)::{{ $test->test_name }}@endif</dd></div>
                    @if (filled($test->notes))
                        <div><dt class="text-xs text-slate-500">备注</dt><dd class="whitespace-pre-wrap break-all text-xs">{{ $test->notes }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-slate-500">代码标记</dt><dd class="font-mono text-xs" data-test-marker>[T{{ $test->number }}]</dd></div>
                </dl>
                @if ($test->features->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach ($test->features as $feature)
                            <a href="{{ $this->featureUrl($feature) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="{{ $feature->title }}">F{{ $feature->number }}</a>
                        @endforeach
                    </div>
                @endif
                @foreach ($test->features as $feature)
                    <div class="mt-5 border-t border-slate-200 pt-3 dark:border-white/10">
                        <a href="{{ $this->featureUrl($feature) }}" class="text-sm font-medium text-slate-950 hover:underline dark:text-white">F{{ $feature->number }} {{ $feature->title }}</a>
                        @if ($feature->flowchart !== null)
                            @include('filament.pages.partials.feature-flowchart', ['feature' => $feature])
                        @endif
                    </div>
                @endforeach
            @else
                <div class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一个测试节点。</div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
