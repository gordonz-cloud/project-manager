<x-filament-panels::page>
    @php
        $record = $this->selectedRecord;
        $presenter = $this->presenter();
    @endphp

    <div class="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <aside class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="border-b border-slate-200 p-3 dark:border-white/10">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="搜索..."
                    aria-label="搜索关系树"
                    class="min-h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-hidden placeholder:text-slate-400 focus:border-slate-500 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                >
            </div>

            <div
                class="max-h-[calc(100vh-12rem)] overflow-y-auto p-2"
                x-data
                x-effect="$wire.selectedKey; $nextTick(() => $el.querySelector('[data-tree-key=\'' + $wire.selectedKey + '\']')?.scrollIntoView({block: 'nearest'}))"
            >
                @forelse ($this->visibleTree as $node)
                    @include('filament.pages.partials.workbench-tree-node', ['node' => $node, 'parentPath' => ''])
                @empty
                    <p class="px-2 py-6 text-center text-xs text-slate-400">{{ $this->isSearching() ? '没有匹配。' : '还没有 Use Case。' }}</p>
                @endforelse
            </div>
        </aside>

        <div class="flex min-w-0 flex-col gap-4 xl:h-[calc(100vh-6rem)]">
        <section class="flex min-h-0 min-w-0 flex-col rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950 xl:basis-[60%]">
            @if ($record)
                @php
                    $status = $presenter->statusLabel($record);
                    $body = $presenter->mainText($record);
                @endphp
                <div class="flex shrink-0 items-start gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-slate-500 dark:text-slate-400">{{ $presenter->typeLabel($record) }}</span>
                            @if (filled($status))
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $status }}</span>
                            @endif
                        </div>
                        <h2 class="mt-2 text-base font-semibold leading-6 text-slate-950 dark:text-white">{{ $presenter->title($record) }}</h2>
                    </div>
                    {{ $this->editAction }}
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    @if ($body['text'] === null)
                        <p class="text-sm text-slate-400">没有正文。</p>
                    @elseif ($body['markdown'])
                        <div class="fi-prose text-sm">{!! str($body['text'])->markdown(['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    @else
                        <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $body['text'] }}</p>
                    @endif

                    @if ($presenter->flowchartMermaid($record) !== null)
                        @include('filament.pages.partials.feature-flowchart', ['feature' => $record])
                    @endif
                </div>
            @else
                <div class="px-5 py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一项。</div>
            @endif
        </section>

        <section class="flex min-h-0 flex-1 flex-col rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950 xl:basis-[40%]">
            <h3 class="shrink-0 border-b border-slate-200 px-4 py-2.5 text-xs font-medium text-slate-500 dark:border-white/10 dark:text-slate-400">最近 Commits</h3>
            <div class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-white/5">
                @forelse ($this->recentCommits as $commit)
                    <div class="flex items-center gap-2 px-4 py-2 text-sm">
                        <span class="shrink-0 font-mono text-xs text-slate-400">{{ substr($commit->hash, 0, 7) }}</span>
                        @if ($commit->feature_id !== null)
                            <button
                                type="button"
                                wire:click="selectNode('feature:{{ $commit->feature_id }}')"
                                class="min-w-0 flex-1 truncate text-left text-slate-950 hover:underline dark:text-white"
                                title="{{ $commit->subject }}"
                            >{{ $commit->subject }}</button>
                        @else
                            <span class="min-w-0 flex-1 truncate text-slate-950 dark:text-white" title="{{ $commit->subject }}">{{ $commit->subject }}</span>
                        @endif
                        <span class="shrink-0 text-xs text-slate-400">{{ $commit->committed_at->diffForHumans() }}</span>
                        <span class="w-28 shrink-0 truncate text-right text-xs text-slate-400" title="{{ $commit->feature?->title }}">
                            {{ $commit->feature?->title ?? '未挂功能' }}
                        </span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-slate-400">还没有 commit。</p>
                @endforelse
            </div>
        </section>
        </div>
    </div>
</x-filament-panels::page>
