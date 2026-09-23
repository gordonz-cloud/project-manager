@php
    $children = $this->treeChildUseCasesByUseCase[$item->id] ?? [];
    $scenarios = $this->treeScenariosByUseCase[$item->id] ?? [];
    $features = $this->treeFeaturesByUseCase[$item->id] ?? [];
    $expanded = $this->expandedTreeUseCases[$item->id] ?? false;
@endphp

<div wire:key="tree-use-case-{{ $item->id }}">
    <div class="flex items-center gap-1">
        <button
            type="button"
            wire:click="toggleTreeUseCase({{ $item->id }})"
            class="flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
            aria-label="{{ $expanded ? '收起子层级' : '展开子层级' }}"
        >
            <x-filament::icon icon="{{ $expanded ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
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

    @if ($expanded)
        <div class="ml-7 border-l border-slate-200 py-0.5 dark:border-white/10">
            @if ($children !== [])
                <p class="px-2 pt-1 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-600">Child Use Cases</p>
                @foreach ($children as $child)
                    @include('filament.pages.partials.use-case-tree-node', ['item' => $child])
                @endforeach
            @endif

            @if ($scenarios !== [])
                <p class="mt-2 border-t border-slate-200 px-2 pt-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:border-white/10 dark:text-slate-600">Scenarios</p>
                @foreach ($scenarios as $scenario)
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
                @endforeach
            @endif

            @if ($features !== [])
                <p class="mt-2 border-t border-slate-200 px-2 pt-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-slate-400 dark:border-white/10 dark:text-slate-600">Features</p>
                @foreach ($features as $feature)
                    @include('filament.pages.partials.feature-tree-node', ['item' => $feature])
                @endforeach
            @endif
        </div>
    @endif
</div>
