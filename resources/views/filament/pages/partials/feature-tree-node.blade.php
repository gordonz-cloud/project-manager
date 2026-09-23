@php
    $nodes = $this->treeImplementationNodesByFeature[$item->id] ?? [];
    $featureTests = $this->treeTestsByFeature[$item->id] ?? [];
    $featureFlowSteps = $this->treeFlowStepsByFeature[$item->id] ?? [];
    $featureCommits = $this->treeCommitsByFeature[$item->id] ?? [];
    $expanded = $this->expandedTreeFeatures[$item->id] ?? false;
@endphp

<div wire:key="tree-feature-{{ $item->id }}">
    <div class="flex items-center gap-1">
        <button
            type="button"
            wire:click="toggleTreeFeature({{ $item->id }})"
            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
            aria-label="{{ $expanded ? '收起实现证据' : '展开实现证据' }}"
        >
            <x-filament::icon icon="{{ $expanded ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
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

    @if ($expanded)
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
