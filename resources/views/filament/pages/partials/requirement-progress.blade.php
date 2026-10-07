{{-- The one status a node shows (RequirementProgress); a pending rule says who it waits on, a pending 分组 how many wait inside it. --}}
@if ($progress)
    @php
        $label = match (true) {
            $progress !== \App\Data\Requirements\RequirementProgress::Pending => $progress->value,
            $requirement->kind === \App\Enums\RequirementKind::Group => '含 '.($this->nodesById[$requirement->id]->rollup->pending() ?? 0).' 待决策',
            $requirement->decider === \App\Enums\RequirementDecider::Boss => '待决策 · 等老板',
            default => '待决策 · 等我',
        };
    @endphp
    <span class="shrink-0 rounded px-1 text-xs {{ $progress->badgeClasses() }}" data-progress="{{ $progress->value }}">{{ $label }}</span>
@endif
