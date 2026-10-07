{{-- The one status a node shows (RequirementProgress); a pending rule says where it stands (等我 / 待发老板 / 等老板回复), a pending parent how many wait inside it. --}}
@if ($progress)
    @php
        $label = match (true) {
            $progress !== \App\Data\Requirements\RequirementProgress::Pending => $progress->value,
            ($this->nodesById[$requirement->id]->children ?? []) !== [] => '含 '.$this->nodesById[$requirement->id]->rollup->pending().' 待决策',
            default => '待决策 · '.(\App\Filament\Pages\RequirementTree::WAITING_ON[$requirement->decisionStage()] ?? '等我'),
        };
    @endphp
    <span class="shrink-0 rounded px-1 text-xs {{ $progress->badgeClasses() }}" data-progress="{{ $progress->value }}">{{ $label }}</span>
@endif
