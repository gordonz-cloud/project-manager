<x-filament-panels::page>
    @php($requirement = $this->selectedRequirement)

    <div class="@container flex flex-wrap items-center gap-3 text-sm" data-requirement-summary>
        <span class="text-slate-500 dark:text-slate-400">全项目</span>
        @include('filament.pages.partials.requirement-rollup', ['rollup' => $this->total])
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
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
                x-data="{ reveal() { $nextTick(() => $el.querySelectorAll('[data-selected]').forEach((selected) => selected.scrollIntoView({ block: 'center' }))) } }"
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
                    @php($changes = $this->changes())
                    <div data-tab="changes">
                        <div class="flex flex-wrap items-center gap-1.5 px-1 pb-3" data-change-ranges>
                            @foreach (\App\Filament\Pages\RequirementTree::CHANGE_RANGES as $key => $label)
                                <button type="button" wire:click="setRange(@js($key))" class="rounded-full px-2.5 py-0.5 text-xs {{ $this->range === $key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300' }}">{{ $label }}</button>
                            @endforeach
                            <span class="ml-auto text-xs text-slate-400">{{ count($this->allChanges) }} 条变化</span>
                        </div>

                        @if ($this->allChanges === [])
                            <p class="px-2 py-6 text-center text-xs text-slate-400">这段时间没有变化。</p>
                        @else
                            <div class="grid gap-3 lg:grid-cols-2">
                                <select wire:model.live="branch" class="block w-full rounded-md border-slate-300 text-sm lg:hidden dark:border-white/10 dark:bg-white/5" data-change-branch-select>
                                    <option value="">全部分支（{{ count($this->allChanges) }}）</option>
                                    @foreach (\App\Data\Requirements\RequirementTreeNode::flattened($this->changeTree()) as $node)
                                        @if ($node->children !== [])
                                            <option value="{{ $node->requirement->id }}">{{ str_repeat('　', $this->depthOf($node->requirement->id)) }}{{ $node->requirement->title }}（{{ $this->changeCounts[$node->requirement->id] }}）</option>
                                        @endif
                                    @endforeach
                                </select>

                                <div class="hidden border-r border-slate-200 pr-2 lg:block lg:max-h-[calc(100vh-18rem)] lg:overflow-y-auto dark:border-white/10" data-change-tree>
                                    @foreach ($this->changeTree() as $node)
                                        @include('filament.pages.partials.requirement-change-node', ['node' => $node, 'depth' => 0])
                                    @endforeach
                                </div>

                                <div class="min-w-0 space-y-4 lg:max-h-[calc(100vh-18rem)] lg:overflow-y-auto" data-change-timeline="{{ count($changes) }}">
                                    @forelse (collect($changes)->groupBy(fn ($change) => $this->localTime($change)->toDateString()) as $day => $dayChanges)
                                        <section wire:key="day-{{ $day }}">
                                            <h3 class="sticky top-0 z-10 bg-white/95 py-1 text-xs font-medium text-slate-500 backdrop-blur dark:bg-slate-950/95 dark:text-slate-400">{{ $day === now(config('app.display_timezone'))->toDateString() ? '今天' : ($day === now(config('app.display_timezone'))->subDay()->toDateString() ? '昨天' : $day) }}</h3>
                                            <ol class="space-y-1">
                                                @foreach ($dayChanges as $change)
                                                    @php($changed = $this->requirementOf($change))
                                                    @continue($changed === null)
                                                    @php($isSelected = $this->selectedNumber === $changed->number)
                                                    <li wire:key="{{ $change->key }}">
                                                        <button type="button" wire:click="selectNode({{ $changed->number }})" data-change="{{ $change->kind }}" @if ($isSelected) data-selected @endif
                                                            class="block w-full rounded-md px-2 py-1.5 text-left {{ $isSelected ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}">
                                                            @if ($path = $this->pathOf($change->requirementId))<p class="truncate text-[11px] opacity-60">{{ $path }}</p>@endif
                                                            <p class="flex min-w-0 items-center gap-2 text-sm">
                                                                <span class="shrink-0 tabular-nums text-xs opacity-60">{{ $this->localTime($change)->format('H:i') }}</span>
                                                                <span class="shrink-0 rounded px-1 text-xs {{ $change->badgeClasses() }}">{{ $change->kind }}</span>
                                                                <span class="min-w-0 truncate {{ $changed->status === \App\Enums\RequirementStatus::Void ? 'line-through opacity-60' : '' }}">{{ $changed->title }}</span>
                                                            </p>
                                                            @if ($change->kind === \App\Data\Requirements\RequirementChange::REWORDED)
                                                                <p class="truncate text-xs opacity-70">原来：<span class="line-through">{{ $change->detail }}</span></p>
                                                            @elseif ($change->detail)
                                                                <p class="text-xs opacity-70">{{ $change->detail }}</p>
                                                            @endif
                                                            @if ($change->reason)<p class="line-clamp-2 text-xs opacity-70">{{ $this->mentions->plain($change->reason) }}</p>@endif
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </section>
                                    @empty
                                        <p class="px-2 py-6 text-center text-xs text-slate-400">这个分支这段时间没有变化。</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif
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
