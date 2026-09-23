<x-filament-panels::page>
    @php
        $statusToneClasses = [
            'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            'danger' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
            'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            'gray' => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300',
        ];

        $stateMachine = $this->selectedStateMachine;
        $stateLabels = $stateMachine === null
            ? collect()
            : collect($stateMachine['states'])->pluck('label', 'value');
    @endphp

    <div class="space-y-4">
        <section class="rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md bg-slate-950 px-2.5 py-1 text-[11px] font-semibold text-white dark:bg-white dark:text-slate-950">
                            关系工作台
                        </span>
                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $this->project->name }}</span>
                    </div>
                    <h1 class="mt-3 text-xl font-semibold text-slate-950 dark:text-white">数据库关系树与详情</h1>
                </div>

                <div class="relative w-full xl:max-w-xl">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4" />
                    </span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="搜索记录、表、状态或 ID..."
                        aria-label="搜索关系节点"
                        class="min-h-10 w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-950 outline-hidden transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-500/15 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    >
                </div>
            </div>

            @if ($this->search !== '')
                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4 dark:border-white/10">
                    @forelse (array_slice($this->searchMatches, 0, 12) as $match)
                        <button
                            type="button"
                            wire:key="search-match-{{ $match->key }}"
                            wire:click="selectNode('{{ $match->key }}')"
                            class="inline-flex max-w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs text-slate-700 transition hover:border-slate-300 hover:bg-white hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:text-white"
                        >
                            <span class="truncate">{{ $match->type }} · {{ $match->title }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">没有匹配节点。</p>
                    @endforelse
                </div>
            @endif
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <aside class="max-h-[52rem] overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950 xl:sticky xl:top-6">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-slate-950 dark:text-white">数据库关系树</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">实体为节点，中间表为关系。</p>
                </div>

                <div class="max-h-[48rem] overflow-y-auto p-3">
                    <div class="flex items-center justify-between px-2 py-2">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">模块</span>
                        <span class="text-[11px] tabular-nums text-slate-400 dark:text-slate-500">{{ count($this->treeModules) }}</span>
                    </div>

                    <div class="space-y-1">
                        @foreach ($this->treeModules as $module)
                            <section wire:key="tree-module-{{ $module['id'] }} rounded-md">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="toggleTreeModule({{ $module['id'] }})"
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                        aria-label="{{ $module['expanded'] ? '收起模块' : '展开模块' }}"
                                    >
                                        <x-filament::icon icon="{{ $module['expanded'] ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="selectModuleScope({{ $module['id'] }})"
                                        class="flex min-h-9 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $module['selected'] ? 'bg-slate-100 dark:bg-white/10' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                    >
                                        <x-filament::icon icon="heroicon-m-rectangle-stack" class="size-4 shrink-0 text-slate-400" />
                                        <span class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-900 dark:text-white">{{ $module['label'] }}</span>
                                        <span class="text-[10px] tabular-nums text-slate-400 dark:text-slate-500">{{ $module['spec_count'] }}</span>
                                    </button>
                                </div>

                                @if ($module['expanded'])
                                    @php
                                        $visibleSpecs = $module['show_all']
                                            ? $module['specs']
                                            : array_slice($module['specs'], 0, 5);
                                    @endphp

                                    <div class="ml-4 border-l border-slate-200 py-1 dark:border-white/10">
                                        @foreach ($visibleSpecs as $spec)
                                            <button
                                                type="button"
                                                wire:key="tree-spec-{{ $spec['id'] }}"
                                                wire:click="selectSpec({{ $spec['id'] }})"
                                                class="w-full rounded-md px-2.5 py-2 text-left transition {{ $spec['selected'] ? 'bg-slate-100 dark:bg-white/10' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                            >
                                                <span class="flex items-start gap-2">
                                                    <span class="mt-1 size-2 shrink-0 rounded-full {{ $spec['status'] === 'active' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex items-center gap-2">
                                                            <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">{{ $spec['label'] }}</span>
                                                            <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ $spec['status_label'] }}</span>
                                                        </span>
                                                        <span class="mt-1 line-clamp-2 text-xs font-medium leading-5 text-slate-900 dark:text-white">{{ $spec['title'] }}</span>
                                                    </span>
                                                </span>
                                            </button>

                                            @if ($spec['selected'])
                                                <div class="ml-4 border-l border-slate-200 py-1 dark:border-white/10">
                                                    @foreach ($this->treeGroups as $group)
                                                                        <section wire:key="tree-group-{{ $group['key'] }}">
                                                                            <button
                                                                                type="button"
                                                                                wire:click="toggleTreeGroup('{{ $group['key'] }}')"
                                                                                class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                                                                            >
                                                                                <x-filament::icon icon="{{ $group['expanded'] ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3 text-slate-400" />
                                                                <span class="min-w-0 flex-1 truncate text-[11px] font-medium text-slate-700 dark:text-slate-200">{{ $group['label'] }}</span>
                                                                <span class="text-[10px] tabular-nums {{ $group['count'] === 0 ? 'text-slate-300 dark:text-slate-700' : 'text-slate-400 dark:text-slate-500' }}">
                                                                    {{ $group['count'] === 0 ? '未建立' : $group['count'] }}
                                                                </span>
                                                                            </button>
                                                                            <p class="pl-7 text-[9px] text-slate-400 dark:text-slate-600">{{ $group['relation'] }}</p>

                                                                            @if ($group['expanded'])
                                                                                @php
                                                                                    $visibleItems = $group['show_all']
                                                                                        ? $group['items']
                                                                                        : array_slice($group['items'], 0, 5);
                                                                                @endphp

                                                                    <div class="mt-1 space-y-0.5 pl-5">
                                                                        @if ($group['count'] === 0)
                                                                            <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无记录</p>
                                                                        @elseif ($group['key'] === 'use_cases')
                                                                            @foreach ($visibleItems as $item)
                                                                                @php
                                                                                    $scenarios = $this->treeScenariosByUseCase[$item->id] ?? [];
                                                                                @endphp
                                                                                <div wire:key="tree-use-case-{{ $item->id }}">
                                                                                    <div class="flex items-center gap-1">
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="toggleTreeUseCase({{ $item->id }})"
                                                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                                                                            aria-label="{{ ($this->expandedTreeUseCases[$item->id] ?? false) ? '收起场景' : '展开场景' }}"
                                                                                        >
                                                                                            <x-filament::icon icon="{{ ($this->expandedTreeUseCases[$item->id] ?? false) ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="selectNode('{{ $item->key }}')"
                                                                                            class="flex min-h-8 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $item->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                        >
                                                                                            <span class="size-1.5 shrink-0 rounded-full bg-sky-400"></span>
                                                                                            <span class="min-w-0 flex-1 truncate text-[11px]">{{ $item->title }}</span>
                                                                                            <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($item) }}</span>
                                                                                        </button>
                                                                                    </div>

                                                                                    @if ($this->expandedTreeUseCases[$item->id] ?? false)
                                                                                        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
                                                                                            @forelse ($scenarios as $scenario)
                                                                                                <button
                                                                                                    type="button"
                                                                                                    wire:key="tree-scenario-{{ $scenario->id }}"
                                                                                                    wire:click="selectNode('{{ $scenario->key }}')"
                                                                                                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $scenario->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                >
                                                                                                    <span class="size-1.5 shrink-0 rounded-full bg-sky-300"></span>
                                                                                                    <span class="min-w-0 flex-1 truncate text-[11px]">{{ $scenario->title }}</span>
                                                                                                    <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($scenario) }}</span>
                                                                                                </button>
                                                                                            @empty
                                                                                                <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无场景</p>
                                                                                            @endforelse

                                                                                            @if (($this->treeFeaturesByUseCase[$item->id] ?? []) !== [])
                                                                                                <p class="mt-2 border-t border-slate-200 px-2 pt-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:border-white/10 dark:text-slate-600">Features</p>
                                                                                                @foreach ($this->treeFeaturesByUseCase[$item->id] as $feature)
                                                                                                    @include('filament.pages.partials.feature-tree-node', ['item' => $feature])
                                                                                                @endforeach
                                                                                            @endif
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        @elseif ($group['key'] === 'unassigned_features')
                                                                            @foreach ($visibleItems as $item)
                                                                                @php
                                                                                    $nodes = $this->treeImplementationNodesByFeature[$item->id] ?? [];
                                                                                    $featureTests = $this->treeTestsByFeature[$item->id] ?? [];
                                                                                    $featureFlowSteps = $this->treeFlowStepsByFeature[$item->id] ?? [];
                                                                                    $featureCommits = $this->treeCommitsByFeature[$item->id] ?? [];
                                                                                @endphp
                                                                                <div wire:key="tree-feature-{{ $item->id }}">
                                                                                    <div class="flex items-center gap-1">
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="toggleTreeFeature({{ $item->id }})"
                                                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                                                                            aria-label="{{ ($this->expandedTreeFeatures[$item->id] ?? false) ? '收起实现节点' : '展开实现节点' }}"
                                                                                        >
                                                                                            <x-filament::icon icon="{{ ($this->expandedTreeFeatures[$item->id] ?? false) ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="selectNode('{{ $item->key }}')"
                                                                                            class="flex min-h-8 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $item->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                        >
                                                                                            <span class="size-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                                                                            <span class="min-w-0 flex-1 truncate text-[11px]">{{ $item->title }}</span>
                                                                                            <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($item) }}</span>
                                                                                        </button>
                                                                                    </div>

                                                                                    @if ($this->expandedTreeFeatures[$item->id] ?? false)
                                                                                        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
                                                                                            <div class="py-1">
                                                                                                <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Implementation Nodes</p>
                                                                                                @forelse ($nodes as $node)
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        wire:key="tree-implementation-node-{{ $node->id }}"
                                                                                                        wire:click="selectNode('{{ $node->key }}')"
                                                                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $node->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                    >
                                                                                                        <span class="size-1.5 shrink-0 rounded-full bg-emerald-300"></span>
                                                                                                        <span class="min-w-0 flex-1 truncate text-[11px]">{{ $node->title }}</span>
                                                                                                        <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($node) }}</span>
                                                                                                    </button>
                                                                                                @empty
                                                                                                    <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无实现节点</p>
                                                                                                @endforelse
                                                                                            </div>

                                                                                            <div class="border-t border-slate-200 py-1 dark:border-white/10">
                                                                                                <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Tests</p>
                                                                                                @forelse ($featureTests as $test)
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        wire:key="tree-feature-test-{{ $test->id }}"
                                                                                                        wire:click="selectNode('{{ $test->key }}')"
                                                                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $test->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                    >
                                                                                                        <span class="size-1.5 shrink-0 rounded-full bg-rose-300"></span>
                                                                                                        <span class="min-w-0 flex-1 truncate text-[11px]">{{ $test->title }}</span>
                                                                                                        <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($test) }}</span>
                                                                                                    </button>
                                                                                                @empty
                                                                                                    <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无测试</p>
                                                                                                @endforelse
                                                                                            </div>

                                                                                            <div class="border-t border-slate-200 py-1 dark:border-white/10">
                                                                                                <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Flow Steps</p>
                                                                                                @forelse ($featureFlowSteps as $step)
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        wire:key="tree-feature-flow-step-{{ $step->id }}"
                                                                                                        wire:click="selectNode('{{ $step->key }}')"
                                                                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $step->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                    >
                                                                                                        <span class="size-1.5 shrink-0 rounded-full bg-amber-300"></span>
                                                                                                        <span class="min-w-0 flex-1 truncate text-[11px]">{{ $step->title }}</span>
                                                                                                        <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($step) }}</span>
                                                                                                    </button>
                                                                                                @empty
                                                                                                    <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无 Flow Step</p>
                                                                                                @endforelse
                                                                                            </div>

                                                                                            <div class="border-t border-slate-200 py-1 dark:border-white/10">
                                                                                                <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Commits</p>
                                                                                                @forelse ($featureCommits as $commit)
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        wire:key="tree-feature-commit-{{ $commit->id }}"
                                                                                                        wire:click="selectNode('{{ $commit->key }}')"
                                                                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $commit->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                    >
                                                                                                        <span class="size-1.5 shrink-0 rounded-full bg-slate-300"></span>
                                                                                                        <span class="min-w-0 flex-1 truncate text-[11px]">{{ $commit->title }}</span>
                                                                                                        <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($commit) }}</span>
                                                                                                    </button>
                                                                                                @empty
                                                                                                    <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无 commit</p>
                                                                                                @endforelse
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        @elseif ($group['key'] === 'data_models')
                                                                            @foreach ($visibleItems as $item)
                                                                                @php
                                                                                    $fields = $this->treeModelFieldsByDataModel[$item->id] ?? [];
                                                                                @endphp
                                                                                <div wire:key="tree-data-model-{{ $item->id }}">
                                                                                    <div class="flex items-center gap-1">
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="toggleTreeDataModel({{ $item->id }})"
                                                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                                                                            aria-label="{{ ($this->expandedTreeDataModels[$item->id] ?? false) ? '收起字段' : '展开字段' }}"
                                                                                        >
                                                                                            <x-filament::icon icon="{{ ($this->expandedTreeDataModels[$item->id] ?? false) ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="selectNode('{{ $item->key }}')"
                                                                                            class="flex min-h-8 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $item->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                        >
                                                                                            <span class="size-1.5 shrink-0 rounded-full bg-violet-400"></span>
                                                                                            <span class="min-w-0 flex-1 truncate text-[11px]">{{ $item->title }}</span>
                                                                                            <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($item) }}</span>
                                                                                        </button>
                                                                                    </div>

                                                                                    @if ($this->expandedTreeDataModels[$item->id] ?? false)
                                                                                        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
                                                                                            <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Model Fields</p>
                                                                                            @forelse ($fields as $field)
                                                                                                <button
                                                                                                    type="button"
                                                                                                    wire:key="tree-model-field-{{ $field->id }}"
                                                                                                    wire:click="selectNode('{{ $field->key }}')"
                                                                                                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $field->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                >
                                                                                                    <span class="size-1.5 shrink-0 rounded-full bg-violet-300"></span>
                                                                                                    <span class="min-w-0 flex-1 truncate text-[11px]">{{ $field->title }}</span>
                                                                                                    <span class="shrink-0 text-[9px] opacity-60">{{ $field->status }}</span>
                                                                                                </button>
                                                                                            @empty
                                                                                                <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无字段</p>
                                                                                            @endforelse
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        @elseif ($group['key'] === 'workflow_runs')
                                                                            @foreach ($visibleItems as $item)
                                                                                @php
                                                                                    $nodeRuns = $this->treeNodeRunsByWorkflowRun[$item->id] ?? [];
                                                                                    $runEvents = $this->treeRunEventsByWorkflowRun[$item->id] ?? [];
                                                                                @endphp
                                                                                <div wire:key="tree-workflow-run-{{ $item->id }}">
                                                                                    <div class="flex items-center gap-1">
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="toggleTreeWorkflowRun({{ $item->id }})"
                                                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                                                                            aria-label="{{ ($this->expandedTreeWorkflowRuns[$item->id] ?? false) ? '收起执行层级' : '展开执行层级' }}"
                                                                                        >
                                                                                            <x-filament::icon icon="{{ ($this->expandedTreeWorkflowRuns[$item->id] ?? false) ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            wire:click="selectNode('{{ $item->key }}')"
                                                                                            class="flex min-h-8 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $item->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                        >
                                                                                            <span class="size-1.5 shrink-0 rounded-full bg-orange-400"></span>
                                                                                            <span class="min-w-0 flex-1 truncate text-[11px]">{{ $item->title }}</span>
                                                                                            <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($item) }}</span>
                                                                                        </button>
                                                                                    </div>

                                                                                    @if ($this->expandedTreeWorkflowRuns[$item->id] ?? false)
                                                                                        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
                                                                                            <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Node Runs</p>
                                                                                            @forelse ($nodeRuns as $nodeRun)
                                                                                                @php
                                                                                                    $nodeEvents = $this->treeRunEventsByNodeRun[$nodeRun->id] ?? [];
                                                                                                @endphp
                                                                                                <div wire:key="tree-node-run-{{ $nodeRun->id }}">
                                                                                                    <div class="flex items-center gap-1">
                                                                                                        <button
                                                                                                            type="button"
                                                                                                            wire:click="toggleTreeNodeRun({{ $nodeRun->id }})"
                                                                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                                                                                                            aria-label="{{ ($this->expandedTreeNodeRuns[$nodeRun->id] ?? false) ? '收起事件' : '展开事件' }}"
                                                                                                        >
                                                                                                            <x-filament::icon icon="{{ ($this->expandedTreeNodeRuns[$nodeRun->id] ?? false) ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
                                                                                                        </button>
                                                                                                        <button
                                                                                                            type="button"
                                                                                                            wire:click="selectNode('{{ $nodeRun->key }}')"
                                                                                                            class="flex min-h-8 min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $nodeRun->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                        >
                                                                                                            <span class="size-1.5 shrink-0 rounded-full bg-orange-300"></span>
                                                                                                            <span class="min-w-0 flex-1 truncate text-[11px]">{{ $nodeRun->title }}</span>
                                                                                                            <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($nodeRun) }}</span>
                                                                                                        </button>
                                                                                                    </div>

                                                                                                    @if ($this->expandedTreeNodeRuns[$nodeRun->id] ?? false)
                                                                                                        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
                                                                                                            <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Run Events</p>
                                                                                                            @forelse ($nodeEvents as $event)
                                                                                                                <button
                                                                                                                    type="button"
                                                                                                                    wire:key="tree-node-run-event-{{ $event->id }}"
                                                                                                                    wire:click="selectNode('{{ $event->key }}')"
                                                                                                                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $event->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                                >
                                                                                                                    <span class="size-1.5 shrink-0 rounded-full bg-orange-200"></span>
                                                                                                                    <span class="min-w-0 flex-1 truncate text-[11px]">{{ $event->title }}</span>
                                                                                                                    <span class="shrink-0 text-[9px] opacity-60">{{ $event->subtitle }}</span>
                                                                                                                </button>
                                                                                                            @empty
                                                                                                                <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无事件</p>
                                                                                                            @endforelse
                                                                                                        </div>
                                                                                                    @endif
                                                                                                </div>
                                                                                            @empty
                                                                                                <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无 Node Run</p>
                                                                                            @endforelse

                                                                                            <div class="border-t border-slate-200 py-1 dark:border-white/10">
                                                                                                <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Run Events</p>
                                                                                                @forelse ($runEvents as $event)
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        wire:key="tree-workflow-run-event-{{ $event->id }}"
                                                                                                        wire:click="selectNode('{{ $event->key }}')"
                                                                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $event->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                                    >
                                                                                                        <span class="size-1.5 shrink-0 rounded-full bg-orange-200"></span>
                                                                                                        <span class="min-w-0 flex-1 truncate text-[11px]">{{ $event->title }}</span>
                                                                                                        <span class="shrink-0 text-[9px] opacity-60">{{ $event->subtitle }}</span>
                                                                                                    </button>
                                                                                                @empty
                                                                                                    <p class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-600">无 Run Event</p>
                                                                                                @endforelse
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        @else
                                                                            @foreach ($visibleItems as $item)
                                                                                <button
                                                                                    type="button"
                                                                                    wire:key="tree-node-{{ $item->key }}"
                                                                                    wire:click="selectNode('{{ $item->key }}')"
                                                                                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-left transition {{ $this->selectedKey === $item->key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
                                                                                >
                                                                                    <span class="size-1.5 shrink-0 rounded-full {{ match ($item->layer) {
                                                                                        'behavior' => 'bg-sky-400',
                                                                                        'solution' => 'bg-violet-400',
                                                                                        'delivery' => 'bg-emerald-400',
                                                                                        'execution' => 'bg-orange-400',
                                                                                        'evidence' => 'bg-rose-400',
                                                                                        default => 'bg-slate-400',
                                                                                    } }}"></span>
                                                                                    <span class="min-w-0 flex-1 truncate text-[11px]">{{ $item->title }}</span>
                                                                                    <span class="shrink-0 text-[9px] opacity-60">{{ $this->statusLabel($item) }}</span>
                                                                                </button>
                                                                            @endforeach
                                                                        @endif
                                                                    </div>

                                                                                @if (! $group['show_all'] && $group['count'] > 5)
                                                                                    <button
                                                                                        type="button"
                                                                                        wire:click="expandTreeGroup('{{ $group['key'] }}')"
                                                                                        class="ml-7 mt-1 text-[10px] font-medium text-slate-500 underline decoration-slate-300 underline-offset-2 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"
                                                                                    >
                                                                                        另外 {{ $group['count'] - 5 }} 条
                                                                                    </button>
                                                                                @endif
                                                                            @endif
                                                                        </section>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach

                                        @if (! $module['show_all'] && $module['spec_count'] > 5)
                                            <button
                                                type="button"
                                                wire:click="expandTreeModule({{ $module['id'] }})"
                                                class="ml-3 mt-1 text-[10px] font-medium text-slate-500 underline decoration-slate-300 underline-offset-2 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"
                                            >
                                                另外 {{ $module['spec_count'] - 5 }} 条 Spec
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                </div>
            </aside>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
                @if ($this->selectedNode)
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-white/10">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $this->selectedNode->type }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ $this->statusLabel($this->selectedNode) }}</span>
                            @if (isset($this->blockedReasons[$this->selectedNode->key]))
                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ $this->blockedReasons[$this->selectedNode->key] }}</span>
                            @elseif (isset($this->gapReasons[$this->selectedNode->key]))
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $this->gapReasons[$this->selectedNode->key] }}</span>
                            @endif
                        </div>
                        <h2 class="mt-3 text-lg font-semibold leading-6 text-slate-950 dark:text-white">{{ $this->selectedNode->title }}</h2>
                        @if ($this->selectedNode->subtitle)
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $this->selectedNode->subtitle }}</p>
                        @endif
                    </div>

                    <div class="max-h-[44rem] space-y-5 overflow-y-auto p-5">
                        <section>
                            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">字段详情</h3>
                            <dl class="mt-3 space-y-4">
                                @forelse ($this->selectedNode->meta as $label => $value)
                                    @if (filled($value))
                                        <div>
                                            <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">{{ $label }}</dt>
                                            <dd class="mt-1 whitespace-pre-wrap break-words text-left text-sm leading-6 text-slate-700 dark:text-slate-300">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : $value }}</dd>
                                        </div>
                                    @endif
                                @empty
                                    <p class="text-sm text-slate-500 dark:text-slate-400">没有更多详情。</p>
                                @endforelse
                            </dl>
                        </section>

                        <section class="border-t border-slate-200 pt-5 dark:border-white/10">
                            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">关系</h3>
                            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h4 class="text-xs font-medium text-slate-600 dark:text-slate-300">上游</h4>
                                    <div class="mt-2 space-y-1">
                                        @forelse ($this->incomingEdges as $edge)
                                            @php
                                                $related = $this->nodeForKey($edge->from);
                                            @endphp
                                            @if ($related)
                                                <button type="button" wire:key="in-{{ $edge->from }}-{{ $edge->to }}-{{ $edge->kind }}" wire:click="selectNode('{{ $related->key }}')" class="w-full rounded-md px-2 py-2 text-left transition hover:bg-slate-50 dark:hover:bg-white/5">
                                                    <span class="block truncate text-xs font-medium text-slate-800 dark:text-slate-200">{{ $related->title }}</span>
                                                    <span class="mt-0.5 block truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $this->relationTable($edge) }} · {{ $this->edgeLabel($edge) }}</span>
                                                </button>
                                            @endif
                                        @empty
                                            <p class="text-xs text-slate-400 dark:text-slate-500">没有上游关系。</p>
                                        @endforelse
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-xs font-medium text-slate-600 dark:text-slate-300">下游</h4>
                                    <div class="mt-2 space-y-1">
                                        @forelse ($this->outgoingEdges as $edge)
                                            @php
                                                $related = $this->nodeForKey($edge->to);
                                            @endphp
                                            @if ($related)
                                                <button type="button" wire:key="out-{{ $edge->from }}-{{ $edge->to }}-{{ $edge->kind }}" wire:click="selectNode('{{ $related->key }}')" class="w-full rounded-md px-2 py-2 text-left transition hover:bg-slate-50 dark:hover:bg-white/5">
                                                    <span class="block truncate text-xs font-medium text-slate-800 dark:text-slate-200">{{ $related->title }}</span>
                                                    <span class="mt-0.5 block truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $this->relationTable($edge) }} · {{ $this->edgeLabel($edge) }}</span>
                                                </button>
                                            @endif
                                        @empty
                                            <p class="text-xs text-slate-400 dark:text-slate-500">没有下游关系。</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="border-t border-slate-200 pt-5 dark:border-white/10">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">状态与转换</h3>
                                @if ($stateMachine)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300">当前：{{ $stateMachine['current_label'] }}</span>
                                @endif
                            </div>

                            @if ($stateMachine)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($stateMachine['states'] as $state)
                                        <span class="rounded-md border px-3 py-2 text-xs font-medium {{ $state['current'] ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400' }}">
                                            {{ $state['label'] }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid gap-2 2xl:grid-cols-2">
                                    @foreach ($stateMachine['transitions'] as $transition)
                                        <div class="flex min-w-0 items-center gap-2 whitespace-nowrap rounded-md border px-3 py-2 text-xs {{ $transition['current'] ? 'border-slate-300 bg-slate-50 dark:border-white/15 dark:bg-white/5' : 'border-slate-200 dark:border-white/10' }}">
                                            <span class="shrink-0 font-medium text-slate-700 dark:text-slate-200">{{ $stateLabels[$transition['from']] ?? $transition['from'] }}</span>
                                            <span class="shrink-0 {{ $transition['tone'] === 'back' ? 'text-amber-500' : ($transition['tone'] === 'danger' ? 'text-red-500' : 'text-slate-400') }}">→</span>
                                            <span class="shrink-0 font-medium text-slate-700 dark:text-slate-200">{{ $stateLabels[$transition['to']] ?? $transition['to'] }}</span>
                                            <span class="ml-auto truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $transition['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">当前对象类型没有配置状态机。</p>
                            @endif
                        </section>

                        @if ($this->stateHistory !== [])
                            <section class="border-t border-slate-200 pt-5 dark:border-white/10">
                                <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">转换历史</h3>
                                <div class="mt-3 space-y-2">
                                    @foreach ($this->stateHistory as $history)
                                        <div class="flex items-start gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5">
                                            <span class="mt-1.5 size-2 shrink-0 rounded-full {{ match ($history['tone']) {
                                                'success' => 'bg-emerald-500',
                                                'danger' => 'bg-red-500',
                                                'warning' => 'bg-amber-500',
                                                default => 'bg-sky-500',
                                            } }}"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-xs font-medium text-slate-800 dark:text-slate-200">{{ $history['label'] }}</span>
                                                <span class="mt-0.5 block text-[10px] tabular-nums text-slate-400 dark:text-slate-500">{{ $history['time'] }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    @if ($url = $this->resourceUrl($this->selectedNode))
                        <div class="border-t border-slate-200 p-4 dark:border-white/10">
                            <a href="{{ $url }}" class="flex min-h-10 items-center justify-center gap-2 rounded-md bg-slate-950 px-3 text-xs font-medium text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" />
                                打开原始记录
                            </a>
                        </div>
                    @endif
                @else
                    <div class="px-5 py-16 text-center text-sm text-slate-500 dark:text-slate-400">从左侧关系树选择一个节点。</div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
