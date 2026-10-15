@php
    $requirement = $node->requirement;
    $isOpen = $node->children !== [] && ($this->expanded[$requirement->id] ?? true);
    $isBranch = $this->branch === $requirement->id;
    $isSelected = $this->selectedNumber === $requirement->number;
@endphp

<div wire:key="heat-{{ $requirement->id }}">
    <div class="flex items-center">
        @if ($node->children !== [])
            <button type="button" wire:click="toggleNode({{ $requirement->id }}, @js($isOpen))" class="flex size-6 shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-900 dark:hover:text-white" aria-label="{{ $isOpen ? '收起' : '展开' }}">
                <x-filament::icon icon="{{ $isOpen ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4" />
            </button>
        @else
            <span class="size-6 shrink-0"></span>
        @endif

        <div role="button"
            wire:click="{{ $node->children === [] ? "selectNode({$requirement->number})" : "toggleBranch({$requirement->id})" }}"
            data-heat-node="{{ $requirement->number }}"
            @if ($isSelected) data-selected @endif
            class="flex min-h-7 min-w-0 flex-1 cursor-pointer items-center gap-1.5 rounded-md px-1.5 text-left text-xs {{ $isSelected ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : ($isBranch ? 'ring-2 ring-orange-400' : 'hover:bg-slate-50 dark:hover:bg-white/5') }}">
            <span class="min-w-0 truncate {{ $requirement->kind === \App\Enums\RequirementKind::Goal ? 'font-medium' : '' }}">{{ $requirement->title }}</span>
            <span class="ml-auto shrink-0 rounded-full px-1.5 tabular-nums {{ $this->heatClasses($requirement->id) }}" data-heat="{{ $this->changeCounts[$requirement->id] ?? 0 }}">{{ $this->changeCounts[$requirement->id] ?? 0 }}</span>
        </div>
    </div>

    @if ($isOpen)
        <div class="ml-3 border-l border-slate-200 pl-1 dark:border-white/10">
            @foreach ($node->children as $child)
                @include('filament.pages.partials.requirement-change-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
