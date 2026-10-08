{{-- The one panel for a selected node, on every tab: each piece of information shows once. --}}
@php
    // A 冲突 has nothing built of its own yet: what is built is the rule it would replace.
    $old = $requirement->status === \App\Enums\RequirementStatus::Conflict ? $requirement->supersedes : null;
    $features = $requirement->linkedFeatures->merge($old?->linkedFeatures ?? [])->unique('id');
    $tests = $requirement->tests->merge($old?->tests ?? [])->unique('id');
    $rationaleRepeats = $this->repeatsDecision($requirement);
    $timeline = $this->timelineOf($requirement);
@endphp

<div data-panel="{{ $requirement->number }}">
    <header data-panel-header>
        <div class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
            <nav class="flex min-w-0 flex-1 flex-wrap gap-1" data-requirement-path>
                @foreach ($requirement->ancestors() as $step)
                    @if (! $loop->first)<span>›</span>@endif
                    <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline" title="{{ $step->title }}">{{ \Illuminate\Support\Str::limit($step->title, 12) }}</button>
                @endforeach
            </nav>
        </div>
        <div x-data="{ open: false }" class="mt-2 flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <h2 x-ref="title" :class="open ? '' : 'line-clamp-2'" class="text-base font-semibold text-slate-950 dark:text-white">{{ $this->mentions->html($requirement->title) }}</h2>
                @if (mb_strlen($requirement->title) > 60)
                    <button type="button" x-on:click="open = ! open" x-text="open ? '收起' : '展开'" class="text-xs text-slate-500 hover:underline">展开</button>
                @endif
            </div>
            @include('filament.pages.partials.requirement-progress', ['requirement' => $requirement, 'progress' => $this->progressOf($requirement)])
        </div>
        @if ($replacement = $requirement->status === \App\Enums\RequirementStatus::Void ? $requirement->supersededBy : null)
            <p class="mt-1 text-xs text-slate-500" data-superseded-by>被<button type="button" wire:click="selectNode({{ $replacement->number }})" class="mx-0.5 text-primary-600 hover:underline dark:text-primary-400">「{{ \Illuminate\Support\Str::limit($replacement->title, 30, '…') }}」</button>取代（{{ $replacement->decided_at?->toDateString() }}）</p>
        @endif
        @if ($requirement->status === \App\Enums\RequirementStatus::Later)
            <div class="mt-1 flex items-start gap-2 text-xs text-slate-500" data-later>
                <p class="min-w-0 flex-1">这期不做，将来要做。什么时候再看：{{ $this->mentions->html($requirement->source ?: $requirement->rationale ?: '未记录') }}</p>
                <button type="button" wire:click="startNow" class="shrink-0 rounded border border-slate-300 px-2 py-0.5 text-slate-700 hover:bg-slate-50 dark:border-white/20 dark:text-slate-200 dark:hover:bg-white/5">现在要做了</button>
            </div>
        @endif
        @if ($requirement->status === \App\Enums\RequirementStatus::Decided)
            <p class="mt-1 text-xs text-slate-500" data-decided>{{ $requirement->decided_by ? trim(($requirement->decided_by === '老板' ? '老板拍板 ' : "{$requirement->decided_by} 拍板 ").$requirement->decided_at?->toDateString()) : '现状（代码）' }}</p>
        @endif
    </header>

    @if (! $timeline->isEmpty())
        @include('filament.pages.partials.requirement-timeline', ['timeline' => $timeline, 'requirement' => $requirement])
    @else
    @if (filled($requirement->rationale) || filled($requirement->source))
        <section class="mt-4" data-panel-why>
            @if ($rationaleRepeats)
                <details data-why-folded>
                    <summary class="cursor-pointer text-xs font-medium text-slate-500">背景（与上面重复，点开看）</summary>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ $this->mentions->html($requirement->rationale) }}</p>
                </details>
            @else
                <h3 class="text-xs font-medium text-slate-500">为什么</h3>
                @if ($requirement->rationale)<p class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ $this->mentions->html($requirement->rationale) }}</p>@endif
            @endif
            @if ($requirement->source)<p class="mt-1 break-all text-xs text-slate-400">来源：{{ $this->mentions->html($requirement->source) }}</p>@endif
        </section>
    @endif
    @endif

    @if ($this->isDecidable($requirement))
        @include('filament.pages.partials.requirement-decide', ['requirement' => $requirement])
    @endif

    <section class="mt-4" data-panel-progress>
        <h3 class="text-xs font-medium text-slate-500">做到哪了</h3>
        @if ($features->isEmpty() && $tests->isEmpty())
            <p class="mt-1 text-sm text-slate-400">还没做</p>
        @else
            <ul class="mt-1 space-y-0.5 text-sm text-slate-700 dark:text-slate-300">
                @foreach ($features as $feature)
                    <li><a href="{{ $this->featureUrl($feature) }}" class="hover:underline" title="F{{ $feature->number }}">{{ $feature->title }} · {{ $feature->status->value }}</a></li>
                @endforeach
            </ul>
            @if ($tests->isNotEmpty())
                <div class="mt-1 flex flex-wrap gap-1">
                    @foreach ($tests as $test)
                        <a href="{{ $this->testUrl($test) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="T{{ $test->number }}">{{ \Illuminate\Support\Str::limit($test->title, 30) }} · {{ $test->last_result->value }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </section>

    @if ($requirement->dependsOn->isNotEmpty() || $requirement->dependents->isNotEmpty())
        <section class="mt-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
            @foreach (['依赖' => $requirement->dependsOn, '被依赖' => $requirement->dependents] as $label => $related)
                @if ($related->isNotEmpty())
                    <div data-requirement-dependencies="{{ $label }}">
                        <h3 class="text-xs font-medium text-slate-500">{{ $label }}</h3>
                        @foreach ($related->sortBy('number') as $other)
                            <button type="button" wire:click="selectNode({{ $other->number }})" class="block text-left hover:underline">{{ $other->title }}</button>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    <details class="mt-4" data-requirement-history>
        <summary class="cursor-pointer text-xs font-medium text-slate-500">历史</summary>
        @include('filament.pages.partials.requirement-history', ['revisions' => $requirement->revisions, 'commits' => $requirement->recentCommits()])
    </details>
</div>
