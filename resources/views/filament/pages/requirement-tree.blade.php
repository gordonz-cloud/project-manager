<x-filament-panels::page>
    @php($requirement = $this->selectedRequirement)

    <div class="flex flex-wrap items-center gap-3 text-sm" data-requirement-summary>
        <span class="text-slate-500 dark:text-slate-400">全项目</span>
        @include('filament.pages.partials.requirement-rollup', ['rollup' => $this->total])
        @foreach (array_slice($this->pendingByDecider, 0, 2) as $label => $items)
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $label }} {{ $items->count() }}</span>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <aside class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-white/10">
                @foreach (\App\Filament\Pages\RequirementTree::TABS as $key => $label)
                    <button type="button" wire:click="setTab(@js($key))" class="rounded-md border px-2 py-1 text-xs {{ $this->tab === $key ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-300 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">
                        {{ $label }}@if ($key === 'pending') （{{ $this->awaitingDecision->count() }}）@endif
                    </button>
                @endforeach
                <span class="ml-auto flex gap-2 text-xs" title="交付状态，由挂的功能和测试算出">
                    @foreach (\App\Data\Requirements\DeliveryStatus::cases() as $delivery)
                        <span class="{{ $delivery->color() }}">● <span class="text-slate-500 dark:text-slate-400">{{ $delivery->value }}</span></span>
                    @endforeach
                </span>
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
                    <div class="space-y-4" data-tab="pending">
                        @foreach ($this->pendingByDecider as $label => $items)
                            <section class="space-y-3" data-pending-section="{{ $label }}">
                                <h3 class="px-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }} ({{ $items->count() }})</h3>
                                @foreach ($items as $item)
                                    @php($old = $item->supersedes)
                                    <article wire:key="pending-{{ $item->id }}" class="rounded-md border border-slate-200 p-3 dark:border-white/10" data-pending="{{ $item->number }}">
                                        <nav class="flex flex-wrap gap-1 text-xs text-slate-500 dark:text-slate-400">
                                            @foreach ($item->ancestors() as $step)
                                                @if (! $loop->first)<span>›</span>@endif
                                                <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline">{{ \Illuminate\Support\Str::limit($step->title, 30) }}</button>
                                            @endforeach
                                        </nav>
                                        <div class="mt-1 grid gap-3 {{ $old ? 'md:grid-cols-2' : '' }}">
                                            @if ($old)
                                                <div class="rounded bg-slate-50 p-2 dark:bg-white/5" data-superseded>
                                                    <p class="text-xs text-slate-500">现行规则 #{{ $old->number }}</p>
                                                    <button type="button" wire:click="selectNode({{ $old->number }})" class="mt-1 text-left text-sm text-slate-800 hover:underline dark:text-slate-200">{{ $old->title }}</button>
                                                    <p class="mt-1 text-xs text-slate-500">{{ $old->source ?: '来源未记' }}</p>
                                                    @if ($old->rationale)<p class="mt-1 whitespace-pre-wrap text-xs text-slate-600 dark:text-slate-400">为什么：{{ $old->rationale }}</p>@endif
                                                </div>
                                            @endif
                                            <div class="rounded p-2 {{ $old ? 'bg-amber-50 dark:bg-amber-500/10' : '' }}">
                                                <p class="flex items-center gap-2 text-xs text-slate-500">#{{ $item->number }} @include('filament.pages.partials.requirement-status', ['requirement' => $item])</p>
                                                <button type="button" wire:click="selectNode({{ $item->number }})" class="mt-1 text-left text-sm font-medium text-slate-950 hover:underline dark:text-white">{{ $item->title }}</button>
                                                <p class="mt-1 text-xs text-slate-500">{{ $item->source ?: '来源未记' }}</p>
                                                @if ($item->rationale)<p class="mt-1 whitespace-pre-wrap text-xs text-slate-600 dark:text-slate-400">为什么：{{ $item->rationale }}</p>@endif
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <p class="mb-1 text-xs text-slate-500">会影响</p>
                                            @include('filament.pages.partials.requirement-links', [
                                                'features' => $item->linkedFeatures->merge($old?->linkedFeatures ?? [])->unique('id'),
                                                'tests' => $item->tests->merge($old?->tests ?? [])->unique('id'),
                                            ])
                                        </div>
                                    </article>
                                @endforeach
                            </section>
                        @endforeach
                        @if ($this->awaitingDecision->isEmpty())
                            <p class="px-2 py-6 text-center text-xs text-slate-400">没有等你拍板的事。</p>
                        @endif
                    </div>
                @else
                    <div class="space-y-3" data-tab="changes">
                        @forelse ($this->recentChanges as $group)
                            <article wire:key="change-{{ $group->requirement->id }}" class="rounded-md border border-slate-200 p-3 dark:border-white/10">
                                <button type="button" wire:click="selectNode({{ $group->requirement->number }})" class="flex w-full items-center gap-2 text-left text-sm font-medium text-slate-950 hover:underline dark:text-white">
                                    <span class="min-w-0 truncate">#{{ $group->requirement->number }} {{ $group->requirement->title }}</span>
                                    @include('filament.pages.partials.requirement-status', ['requirement' => $group->requirement])
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
                @php($delivery = $this->deliveryOf($requirement))
                <nav class="flex flex-wrap gap-1 text-xs text-slate-500 dark:text-slate-400" data-requirement-path>
                    @foreach ($requirement->ancestors() as $step)
                        @if (! $loop->first)<span>›</span>@endif
                        <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline">{{ \Illuminate\Support\Str::limit($step->title, 30) }}</button>
                    @endforeach
                </nav>
                <h2 class="mt-2 flex items-start gap-2 text-base font-semibold text-slate-950 dark:text-white">
                    <span>{{ $requirement->title }}</span>
                    @include('filament.pages.partials.requirement-status', ['requirement' => $requirement])
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
    </div>
</x-filament-panels::page>
