<x-filament-panels::page>
    @php($requirement = $this->selectedRequirement)

    <div class="@container flex flex-wrap items-center gap-3 text-sm" data-requirement-summary>
        <span class="text-slate-500 dark:text-slate-400">全项目</span>
        @include('filament.pages.partials.requirement-rollup', ['rollup' => $this->total])
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,11fr)_minmax(0,9fr)]">
        <aside class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-white/10">
                @foreach (\App\Filament\Pages\RequirementTree::TABS as $key => $label)
                    <button type="button" wire:click="setTab(@js($key))" class="rounded-md border px-2 py-1 text-xs {{ $this->tab === $key ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-300 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">
                        @php($count = $key === 'pending' ? $this->awaitingDecision->count() : (isset(\App\Filament\Pages\RequirementTree::LEAF_TABS[$key]) ? count($this->leavesIn($key)) : null))
                        {{ $label.($count === null ? '' : "（{$count}）") }}
                    </button>
                @endforeach
            </div>

            <div class="max-h-[calc(100vh-14rem)] overflow-y-auto p-2"
                x-data="{ reveal() { $nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'center' })) } }"
                x-init="reveal()" x-on:reveal-selected.window="reveal()">
                @if ($this->tab === 'overview')
                    <div data-tab="overview">
                        @forelse ($this->placedRoots() as $node)
                            @include('filament.pages.partials.requirement-tree-node', ['node' => $node, 'depth' => 0])
                        @empty
                            <p class="px-2 py-6 text-center text-xs text-slate-400">还没有目标。用 requirements:save 建。</p>
                        @endforelse

                        @if ($unfiled = $this->unfiledRoots())
                            <div class="mt-3 border-t border-slate-200 pt-2 dark:border-white/10" data-unfiled>
                                <button type="button" wire:click="$toggle('showUnfiled')" class="@container flex min-h-8 w-full items-center gap-2 rounded-md px-1 py-1 text-left text-sm font-medium text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5">
                                    <x-filament::icon icon="{{ $this->showUnfiled ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4 shrink-0 text-slate-400" />
                                    <span class="flex-1">未归类（{{ count($unfiled) }}，旧需求清单，还没挂到目标下）</span>
                                    @include('filament.pages.partials.requirement-rollup', ['rollup' => \App\Data\Requirements\RequirementTreeNode::total($unfiled)])
                                </button>
                                @if ($this->showUnfiled)
                                    @foreach ($unfiled as $node)
                                        @include('filament.pages.partials.requirement-tree-node', ['node' => $node, 'depth' => 1])
                                    @endforeach
                                @endif
                            </div>
                        @endif
                    </div>
                @elseif ($this->tab !== 'changes')
                    <div data-tab="{{ $this->tab }}">
                        @if ($this->tab === 'todo')<p class="px-1 pb-2 text-xs text-slate-500">已定还没做完的，树里从上往下按依赖顺序做。</p>@endif
                        @if ($this->tab === 'accepting')<p class="px-1 pb-2 text-xs text-slate-500">功能都写完了、至少一个在验证中，等你看过点「验收通过」。</p>@endif
                        @forelse ($this->filteredTree as $node)
                            @include('filament.pages.partials.requirement-tree-node', ['node' => $node, 'depth' => 0])
                        @empty
                            <p class="px-2 py-6 text-center text-xs text-slate-400">{{ ['pending' => '这里没有要决定的事。', 'todo' => '没有待做的规则。', 'accepting' => '没有待验收的规则。', 'later' => '没有以后做的规则。'][$this->tab] }}</p>
                        @endforelse
                    </div>
                @else
                    <div class="space-y-3" data-tab="changes">
                        @forelse ($this->recentChanges as $group)
                            <article wire:key="change-{{ $group->requirement->id }}" @if ($this->selectedNumber === $group->requirement->number) data-selected @endif class="rounded-md border p-3 {{ $this->selectedNumber === $group->requirement->number ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 dark:border-white/10' }}">
                                <button type="button" wire:click="selectNode({{ $group->requirement->number }})" class="flex w-full items-center gap-2 text-left text-sm font-medium text-slate-950 hover:underline dark:text-white">
                                    <span class="min-w-0 truncate">{{ $group->requirement->title }}</span>
                                    @include('filament.pages.partials.requirement-progress', ['requirement' => $group->requirement, 'progress' => $this->progressOf($group->requirement)])
                                    <span class="ml-auto shrink-0 text-xs font-normal text-slate-400">{{ $group->latestAt()?->diffForHumans() }}</span>
                                </button>
                                @include('filament.pages.partials.requirement-history', ['revisions' => $group->revisions, 'commits' => $group->commits])
                            </article>
                        @empty
                            <p class="px-2 py-6 text-center text-xs text-slate-400">还没有变化。</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </aside>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-white/10 dark:bg-slate-950 xl:max-h-[calc(100vh-10rem)] xl:overflow-y-auto">
            @if ($requirement)
                @include('filament.pages.partials.requirement-panel', ['requirement' => $requirement])
            @else
                <div class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一条需求。</div>
            @endif
        </section>
    </div>

    @if (in_array($this->tab, ['overview', 'pending'], true) && $this->awaitingDecision->isNotEmpty())
        <div class="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white/95 px-4 py-2 text-sm shadow-lg backdrop-blur dark:border-white/10 dark:bg-slate-950/95" data-decision-bar>
            @php($preview = $this->batchPreview)
            <span class="tabular-nums text-slate-700 dark:text-slate-200">已选 {{ $this->drafts->count() }} / {{ $this->awaitingDecision->count() }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400" data-batch-preview>{{ $this->drafts->isEmpty() ? '选了的只是草稿，点「确认这一批」才生效；没选的就是先不定。' : '这批会：'.$preview->summary() }}</span>
            <x-filament::button size="sm" class="ml-auto" wire:click="confirmAllDrafts" wire:confirm="{{ $this->mentions->plain(implode(PHP_EOL, ['这批会：'.$preview->summary(), '', ...$preview->examples, $this->drafts->count() > count($preview->examples) ? '…' : ''])) }}" :disabled="$this->drafts->isEmpty()">确认这一批</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
