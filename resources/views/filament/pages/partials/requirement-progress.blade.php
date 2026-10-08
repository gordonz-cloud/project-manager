{{-- The one status a node shows (RequirementProgress); a pending parent says how many wait inside it. --}}
@if ($progress)
    @php($children = $this->nodesById[$requirement->id]->children ?? [])
    <span class="shrink-0 rounded px-1 text-xs {{ $progress->badgeClasses() }}" data-progress="{{ $progress->value }}">{{ $progress === \App\Data\Requirements\RequirementProgress::Pending && $children !== [] ? '待决策（'.$this->nodesById[$requirement->id]->rollup->pending().'）' : $progress->value }}</span>
@endif
