@php
    $requirement = $node->requirement;
    $isOpen = $node->children !== [] && ($this->expanded[$requirement->id] ?? $depth === 0);
    $isSelected = $this->selectedNumber === $requirement->number;
    $isVoid = $requirement->status === \App\Enums\RequirementStatus::Void;
@endphp

<div wire:key="req-{{ $requirement->id }}">
    <div class="flex items-center">
        @if ($node->children !== [])
            <button type="button" wire:click="toggleNode({{ $requirement->id }}, @js($isOpen))" class="flex size-7 shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-900 dark:hover:text-white" aria-label="{{ $isOpen ? '收起' : '展开' }}">
                <x-filament::icon icon="{{ $isOpen ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-4" />
            </button>
        @else
            <span class="size-7 shrink-0"></span>
        @endif

        <div
            role="button"
            wire:click="selectNode({{ $requirement->number }})"
            data-requirement-number="{{ $requirement->number }}"
            class="@container flex min-h-8 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-left text-sm transition {{ $isSelected ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }} {{ $isVoid && ! $isSelected ? 'text-slate-400 line-through dark:text-slate-500' : '' }}"
        >
            @if ($requirement->kind && $requirement->kind !== \App\Enums\RequirementKind::Rule)
                <span class="shrink-0 text-xs opacity-60">{{ $requirement->kind->value }}</span>
            @endif
            <span class="min-w-0 truncate {{ $requirement->kind === \App\Enums\RequirementKind::Goal ? 'font-medium' : '' }}">{{ $requirement->title }}</span>
            @include('filament.pages.partials.requirement-progress', ['requirement' => $requirement, 'progress' => $node->progress])
            @if ($node->children !== [])
                <span class="ml-auto">@include('filament.pages.partials.requirement-rollup', ['rollup' => $node->rollup])</span>
            @endif
        </div>
    </div>

    @if ($isOpen)
        <div class="ml-3.5 border-l border-slate-200 pl-1 dark:border-white/10">
            @foreach ($node->children as $child)
                @include('filament.pages.partials.requirement-tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
