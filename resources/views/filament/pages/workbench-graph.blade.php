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

            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto p-2">
                @forelse ($this->visibleTree as $node)
                    @include('filament.pages.partials.workbench-tree-node', ['node' => $node, 'parentPath' => ''])
                @empty
                    <p class="px-2 py-6 text-center text-xs text-slate-400">{{ $this->isSearching() ? '没有匹配。' : '还没有 Use Case。' }}</p>
                @endforelse
            </div>
        </aside>

        <section class="min-w-0 self-start rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950 xl:sticky xl:top-6">
            @if ($record)
                @php
                    $status = $presenter->statusLabel($record);
                    $body = $presenter->mainText($record);
                @endphp
                <div class="flex items-start gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10">
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

                <div class="max-h-[calc(100vh-14rem)] overflow-y-auto px-5 py-4">
                    @if ($body['text'] === null)
                        <p class="text-sm text-slate-400">没有正文。</p>
                    @elseif ($body['markdown'])
                        <div class="fi-prose text-sm">{!! str($body['text'])->markdown(['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    @else
                        <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $body['text'] }}</p>
                    @endif

                    @if (($mermaid = $presenter->flowchartMermaid($record)) !== null)
                        <div
                            wire:key="flowchart-{{ $record->getKey() }}-{{ md5($mermaid) }}"
                            wire:ignore
                            data-flowchart
                            data-source="{{ $mermaid }}"
                            x-data
                            x-init="
                                const draw = async () => { $el.innerHTML = (await window.mermaid.render('flowchart-' + Date.now(), $el.dataset.source)).svg };
                                window.mermaid ? draw() : document.addEventListener('mermaid:ready', draw, { once: true });
                            "
                            class="mt-4 overflow-x-auto"
                        ></div>
                        @if (filled($record->flowchart->pseudocode))
                            <pre class="mt-4 overflow-x-auto whitespace-pre-wrap rounded-md bg-slate-50 p-3 font-mono text-xs leading-5 text-slate-700 dark:bg-white/5 dark:text-slate-300" data-pseudocode>{{ $record->flowchart->pseudocode }}</pre>
                        @endif
                    @endif
                </div>
            @else
                <div class="px-5 py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一项。</div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
