@php
    $path = $parentPath === '' ? $node->key : $parentPath.'>'.$node->key;
    $isOpen = $node->children !== [] && ($this->isSearching() || ($this->expanded[$path] ?? false));
    $isSelected = ! $node->isFolder && $this->selectedKey === $node->key;
    $dotClass = match ($node->tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500',
        'info', 'primary' => 'bg-sky-500',
        'gray' => 'bg-slate-400',
        default => null,
    };
@endphp

<div wire:key="tree-{{ $path }}">
    <div class="flex items-center">
        @if ($node->children !== [])
            <button
                type="button"
                wire:click="toggleNode(@js($path))"
                class="flex size-6 shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-900 dark:hover:text-white"
                aria-label="{{ $isOpen ? '收起' : '展开' }}"
            >
                <x-filament::icon icon="{{ $isOpen ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right' }}" class="size-3.5" />
            </button>
        @else
            <span class="size-6 shrink-0"></span>
        @endif

        <button
            type="button"
            @if ($node->isFolder)
                wire:click="toggleNode(@js($path))"
            @else
                wire:click="selectNode(@js($node->key))"
            @endif
            class="flex min-h-7 min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 text-left transition {{ $isSelected ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950' : 'hover:bg-slate-50 dark:hover:bg-white/5' }}"
        >
            <x-filament::icon icon="{{ $node->icon }}" class="size-3.5 shrink-0 {{ $isSelected ? '' : 'text-slate-400' }}" />
            <span class="min-w-0 flex-1 truncate text-xs {{ $node->isFolder ? 'text-slate-500 dark:text-slate-400' : '' }}">{{ $node->label }}</span>
            @if ($node->badge !== null)
                <span class="shrink-0 truncate text-[10px] tabular-nums opacity-60">{{ $node->badge }}</span>
            @elseif ($dotClass !== null)
                <span class="size-1.5 shrink-0 rounded-full {{ $dotClass }}"></span>
            @endif
        </button>
    </div>

    @if ($isOpen)
        <div class="ml-3 border-l border-slate-200 pl-1 dark:border-white/10">
            @foreach ($node->children as $child)
                @include('filament.pages.partials.workbench-tree-node', ['node' => $child, 'parentPath' => $path])
            @endforeach
        </div>
    @endif
</div>
