{{-- The one status a node shows (RequirementProgress); a pending one says who it waits on. --}}
@if ($progress)
    @php($waitsOn = $progress === \App\Data\Requirements\RequirementProgress::Pending ? ($requirement->decider === \App\Enums\RequirementDecider::Boss ? ' · 等老板' : ' · 等我') : '')
    <span class="shrink-0 rounded px-1 text-xs {{ $progress->badgeClasses() }}" data-progress="{{ $progress->value }}">{{ $progress->value.$waitsOn }}</span>
@endif
