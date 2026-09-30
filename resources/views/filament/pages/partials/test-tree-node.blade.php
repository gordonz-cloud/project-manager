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
            <span class="shrink-0 font-mono text-xs opacity-60">#{{ $test->number }}</span>
            <span class="min-w-0 shrink truncate">{{ $test->title }}</span>
            @if (filled($test->expected))
                <span class="min-w-0 flex-1 truncate text-xs opacity-50" title="{{ $test->expected }}">→ {{ $test->expected }}</span>
            @else
                <span class="flex-1"></span>
            @endif
            @if ($test->priority)
                <span class="shrink-0 text-xs opacity-70">{{ $test->priority->value }}</span>
            @endif
            @if ($test->auto && $test->auto !== \App\Enums\TestAuto::Yes)
                <span class="shrink-0 text-xs opacity-70">{{ $test->auto->value }}</span>
            @endif
            @foreach ($test->features as $feature)
                <a href="{{ $this->featureUrl($feature) }}" wire:click.stop class="shrink-0 rounded bg-slate-100 px-1 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="{{ $feature->title }}">F{{ $feature->number }}</a>
            @endforeach
            @if ($node->children !== [])
                @include('filament.pages.partials.test-rollup', ['rollup' => $node->rollup])
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
