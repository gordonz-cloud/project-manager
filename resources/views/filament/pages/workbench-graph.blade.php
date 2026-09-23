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

                    @if ($record instanceof \App\Models\RequestReply && ($steps = $presenter->callSteps($record)) !== [])
                        <table class="mt-4 w-full table-fixed text-left text-xs">
                            <thead class="text-slate-500 dark:text-slate-400">
                                <tr class="border-b border-slate-200 dark:border-white/10">
                                    <th class="w-8 py-2 pr-2">#</th>
                                    <th class="py-2 pr-2">文件::函数</th>
                                    <th class="py-2 pr-2">输入</th>
                                    <th class="py-2 pr-2">变化</th>
                                    <th class="py-2">输出</th>
                                </tr>
                            </thead>
                            <tbody class="align-top text-slate-700 dark:text-slate-300">
                                @foreach ($steps as $index => $step)
                                    @php($node = $step['node'])
                                    <tr @class(['border-b border-slate-100 dark:border-white/5', 'text-red-600 dark:text-red-400' => $step['failureCondition'] !== null]) data-step="{{ $node->id }}">
                                        <td class="py-2 pr-2">{{ $index + 1 }}</td>
                                        <td class="break-words py-2 pr-2" style="padding-left: {{ $step['depth'] * 1 }}rem">
                                            @if ($step['failureCondition'] !== null)
                                                <span class="font-medium" data-failure>✗ {{ $step['failureCondition'] }}</span><br>
                                            @endif
                                            <span class="font-mono">{{ filled($node->file) ? $node->file.(filled($node->function) ? "::{$node->function}" : '') : $node->title }}</span>
                                            @if (filled($node->file))
                                                <span class="block text-[11px] text-slate-400">{{ $node->title }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-pre-wrap break-words py-2 pr-2 font-mono">{{ $node->input }}</td>
                                        <td class="whitespace-pre-wrap break-words py-2 pr-2">{{ $node->change }}</td>
                                        <td class="whitespace-pre-wrap break-words py-2 font-mono">{{ $node->output }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                <div class="px-5 py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一项。</div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
