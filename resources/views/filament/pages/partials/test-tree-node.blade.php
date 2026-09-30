@php
    $test = $node->test;
    $isOpen = $node->children !== [] && ($this->isNarrowed() || ($this->expanded[$test->id] ?? false));
    $isSelected = $this->selectedNumber === $test->number;
    $dotClass = match ($node->state) {
        \App\Data\Tests\TestNodeState::Passed => 'text-emerald-500',
        \App\Data\Tests\TestNodeState::Failed => 'text-red-500',
        \App\Data\Tests\TestNodeState::Blocked => 'text-orange-500',
        \App\Data\Tests\TestNodeState::FakeGreen => 'text-amber-500',
        default => 'text-slate-400',
    };
@endphp

<div wire:key="test-{{ $test->id }}">
    <div class="flex items-center">
        @if ($node->children !== [])
            <button type="button" wire:click="toggleNode({{ $test->id }})" class="flex size-7 shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-900 dark:hover:text-white" aria-label="{{ $isOpen ? '收起' : '展开' }}">
                <x-filament::icon icon="{{ $isOpen ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4" />
            </button>
        @else
            <span class="size-7 shrink-0"></span>
        @endif

        <div
            role="button"
            wire:click="selectNode({{ $test->number }})"
            data-test-number="{{ $test->number }}"
            class="flex min-h-8 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-left text-sm transition {{ $isSelected ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
        >
            <span class="w-4 shrink-0 text-center {{ $dotClass }}" title="{{ $node->state->label() }}">{{ $node->state->symbol() }}</span>
            <span class="min-w-0 truncate">{{ $test->title }}</span>
            @if ($test->lacksEvidence())
                <span class="shrink-0 text-xs opacity-50">缺测试</span>
            @endif
            @if ($node->children !== [] && ! $isOpen)
                <span class="inline-flex shrink-0 gap-1 text-xs tabular-nums">
                    @if ($node->rollup->failed)<span class="text-red-600 dark:text-red-400">✗{{ $node->rollup->failed }}</span>@endif
                    @if ($node->rollup->gaps)<span class="text-slate-500">○{{ $node->rollup->gaps }}</span>@endif
                </span>
            @endif
        </div>
    </div>

    @if ($isOpen)
        <div class="ml-3.5 border-l border-slate-200 pl-1 dark:border-white/10">
            @foreach ($node->children as $child)
                @include('filament.pages.partials.test-tree-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
