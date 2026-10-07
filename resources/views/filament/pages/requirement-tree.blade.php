<x-filament-panels::page>
    @php($requirement = $this->selectedRequirement)

    <div class="flex flex-wrap items-center gap-3 text-sm" data-requirement-summary>
        <span class="text-slate-500 dark:text-slate-400">全项目</span>
        @include('filament.pages.partials.requirement-rollup', ['rollup' => $this->total])
        @foreach (['me', 'boss'] as $key)
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ \App\Filament\Pages\RequirementTree::WAITING_ON[$key] }} {{ $this->waitingOnCount($key) }}</span>
        @endforeach
    </div>

    <div class="grid gap-4 {{ $this->tab === 'pending' ? '' : 'xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' }}">
        <aside class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-white/10">
                @foreach (\App\Filament\Pages\RequirementTree::TABS as $key => $label)
                    <button type="button" wire:click="setTab(@js($key))" class="rounded-md border px-2 py-1 text-xs {{ $this->tab === $key ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-300 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">
                        @php($count = match ($key) { 'pending' => $this->awaitingDecision->count(), 'todo' => count($this->todo), default => null })
                        {{ $label.($count === null ? '' : "（{$count}）") }}
                    </button>
                @endforeach
            </div>

            <div class="max-h-[calc(100vh-14rem)] overflow-y-auto p-2">
                @if ($this->tab === 'overview')
                    <div data-tab="overview">
                        @forelse ($this->placedRoots() as $node)
                            @include('filament.pages.partials.requirement-tree-node', ['node' => $node, 'depth' => 0])
                        @empty
                            <p class="px-2 py-6 text-center text-xs text-slate-400">还没有目标。用 requirements:save 建。</p>
                        @endforelse

                        @if ($unfiled = $this->unfiledRoots())
                            <div class="mt-3 border-t border-slate-200 pt-2 dark:border-white/10" data-unfiled>
                                <button type="button" wire:click="$toggle('showUnfiled')" class="flex min-h-8 w-full items-center gap-2 rounded-md px-1 py-1 text-left text-sm font-medium text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5">
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
                @elseif ($this->tab === 'pending')
                    <div class="space-y-3" data-tab="pending">
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach (\App\Filament\Pages\RequirementTree::WAITING_ON as $key => $label)
                                <button type="button" wire:click="setWaitingOn(@js($key))" class="rounded-md border px-2 py-1 text-xs {{ $this->waitingOn === $key ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-300 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">{{ $label }} {{ $this->waitingOnCount($key) }}</button>
                            @endforeach
                            <span class="ml-auto text-sm tabular-nums text-slate-600 dark:text-slate-300" data-drafted>已选 {{ $this->draftedCount() }} / {{ $this->pendingCards->count() }}</span>
                            <x-filament::button size="sm" wire:click="confirmBatch" wire:confirm="把已选的 {{ $this->draftedCount() }} 条一次写进需求树？" :disabled="$this->draftedCount() === 0">确认这一批</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="toggleBossQuestions">给老板的问题单</x-filament::button>
                        </div>

                        @if ($this->showBossQuestions)
                            <div x-data class="rounded-lg border border-slate-200 p-3 dark:border-white/10" data-boss-questions>
                                <div class="mb-2 flex items-center gap-2 text-xs text-slate-500">
                                    <span>所有等老板的问题，复制发给老板；老板答了，在「等老板」里选他的答案再确认。</span>
                                    <x-filament::button size="xs" color="gray" class="ml-auto" x-on:click="navigator.clipboard.writeText($refs.text.value); $el.innerText = '已复制'">复制</x-filament::button>
                                </div>
                                <textarea x-ref="text" readonly rows="16" class="block w-full rounded-md border border-slate-200 p-2 font-mono text-xs dark:border-white/10 dark:bg-white/5">{{ $this->bossQuestions }}</textarea>
                            </div>
                        @endif

                        @forelse ($this->pendingCards as $item)
                            @include('filament.pages.partials.requirement-decision-card', ['requirement' => $item])
                        @empty
                            <p class="px-2 py-6 text-center text-xs text-slate-400">这里没有要决定的事。</p>
                        @endforelse
                    </div>
                @elseif ($this->tab === 'todo')
                    <div data-tab="todo">
                        <p class="px-1 pb-2 text-xs text-slate-500">已定、还没做完的规则，按这个顺序做（被依赖的在前）。说「动工」时从上往下拿。</p>
                        <ol class="space-y-0.5">
                            @forelse ($this->todo as $node)
                                <li wire:key="todo-{{ $node->requirement->id }}" role="button" wire:click="selectNode({{ $node->requirement->number }})" data-todo="{{ $node->requirement->number }}"
                                    class="flex min-h-8 cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm {{ $this->selectedNumber === $node->requirement->number ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}">
                                    <span class="w-6 shrink-0 text-right text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</span>
                                    @include('filament.pages.partials.requirement-progress', ['requirement' => $node->requirement, 'progress' => $node->progress])
                                    <span class="min-w-0 truncate">{{ $node->requirement->title }}</span>
                                    <span class="ml-auto shrink-0 truncate text-xs text-slate-400">{{ \Illuminate\Support\Str::limit(implode(' › ', $this->pathOf($node->requirement)), 40) }}</span>
                                </li>
                            @empty
                                <li class="px-2 py-6 text-center text-xs text-slate-400">没有待做的规则。</li>
                            @endforelse
                        </ol>
                    </div>
                @else
                    <div class="space-y-3" data-tab="changes">
                        @forelse ($this->recentChanges as $group)
                            <article wire:key="change-{{ $group->requirement->id }}" class="rounded-md border border-slate-200 p-3 dark:border-white/10">
                                <button type="button" wire:click="selectNode({{ $group->requirement->number }})" class="flex w-full items-center gap-2 text-left text-sm font-medium text-slate-950 hover:underline dark:text-white">
                                    <span class="min-w-0 truncate">#{{ $group->requirement->number }} {{ $group->requirement->title }}</span>
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

        @unless ($this->tab === 'pending')
        <section class="min-w-0 rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-white/10 dark:bg-slate-950 xl:max-h-[calc(100vh-10rem)] xl:overflow-y-auto">
            @if ($requirement)
                @php($delivery = $this->deliveryOf($requirement))
                @if ($this->isDecidable($requirement))
                    <div class="mb-4" data-detail-decision>
                        @include('filament.pages.partials.requirement-decision-card', ['requirement' => $requirement])
                    </div>
                @endif
                <nav class="flex flex-wrap gap-1 text-xs text-slate-500 dark:text-slate-400" data-requirement-path>
                    @foreach ($requirement->ancestors() as $step)
                        @if (! $loop->first)<span>›</span>@endif
                        <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline">{{ \Illuminate\Support\Str::limit($step->title, 30) }}</button>
                    @endforeach
                </nav>
                <h2 class="mt-2 flex items-start gap-2 text-base font-semibold text-slate-950 dark:text-white">
                    <span>{{ $requirement->title }}</span>
                    @include('filament.pages.partials.requirement-progress', ['requirement' => $requirement, 'progress' => $this->progressOf($requirement)])
                </h2>
                <dl class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                    <div class="flex gap-6">
                        <div><dt class="text-xs text-slate-500">编号</dt><dd>#{{ $requirement->number }}</dd></div>
                        <div><dt class="text-xs text-slate-500">层级</dt><dd>{{ $requirement->kind?->value ?? '未归类' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">拍板</dt><dd>{{ $requirement->status->value }}</dd></div>
                        <div><dt class="text-xs text-slate-500">交付</dt><dd data-requirement-delivery class="{{ $delivery?->color() }}">{{ $delivery?->value ?? '—' }}</dd></div>
                    </div>
                    <div><dt class="text-xs text-slate-500">为什么</dt><dd class="whitespace-pre-wrap">{{ $requirement->rationale ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">来源</dt><dd class="break-all">{{ $requirement->source ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">谁定的 / 何时</dt><dd>{{ $requirement->decided_by ?: '—' }} · {{ $requirement->decided_at?->toDateString() ?? '—' }}</dd></div>
                    @if ($requirement->decider)<div><dt class="text-xs text-slate-500">等谁拍板</dt><dd>{{ $requirement->decider->value }}</dd></div>@endif
                    @if ($requirement->supersedes)
                        <div><dt class="text-xs text-slate-500">要取代</dt><dd><button type="button" wire:click="selectNode({{ $requirement->supersedes->number }})" class="text-left hover:underline">#{{ $requirement->supersedes->number }} {{ $requirement->supersedes->title }}</button></dd></div>
                    @endif
                    @foreach (['依赖' => $requirement->dependsOn, '被依赖' => $requirement->dependents] as $label => $related)
                        @if ($related->isNotEmpty())
                            <div data-requirement-dependencies="{{ $label }}"><dt class="text-xs text-slate-500">{{ $label }}</dt>
                                @foreach ($related->sortBy('number') as $other)
                                    <dd><button type="button" wire:click="selectNode({{ $other->number }})" class="text-left hover:underline">#{{ $other->number }} {{ $other->title }}</button></dd>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </dl>
                <div class="mt-4">
                    <h3 class="mb-1 text-xs font-medium text-slate-500">功能与测试</h3>
                    @include('filament.pages.partials.requirement-links', ['features' => $requirement->linkedFeatures, 'tests' => $requirement->tests])
                </div>
                <div class="mt-4" data-requirement-history>
                    <h3 class="text-xs font-medium text-slate-500">历史与最近 commit</h3>
                    @include('filament.pages.partials.requirement-history', ['revisions' => $requirement->revisions, 'commits' => $requirement->recentCommits()])
                </div>
            @else
                <div class="py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧选择一条需求。</div>
            @endif
        </section>
        @endunless
    </div>

    @if ($this->tab === 'overview' && $this->awaitingDecision->isNotEmpty())
        <div class="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white/95 px-4 py-2 text-sm shadow-lg backdrop-blur dark:border-white/10 dark:bg-slate-950/95" data-decision-bar>
            <span class="tabular-nums text-slate-700 dark:text-slate-200">已选 {{ $this->drafts->count() }} / {{ $this->awaitingDecision->count() }} 待决策</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">确认的是你所有已选的（包括在待决策页里选的），没选的不动。</span>
            <x-filament::button size="sm" class="ml-auto" wire:click="confirmAllDrafts" wire:confirm="把已选的 {{ $this->drafts->count() }} 条一次写进需求树？" :disabled="$this->drafts->isEmpty()">确认这一批</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
