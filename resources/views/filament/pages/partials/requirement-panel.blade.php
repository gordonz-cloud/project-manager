{{-- The one panel for a selected node, on every tab: a pending node shows only what the decision needs, any other node its why, source and how far it is built. --}}
@php
    $timeline = $this->timelineOf($requirement);
    $source = \App\Models\Requirement::briefSource($requirement->source)
        ?? ($requirement->decided_by ? trim("{$requirement->decided_by} 拍板 {$requirement->decided_at?->toDateString()}") : '没有文档，是代码现状');
@endphp

<div data-panel="{{ $requirement->number }}">
    <header data-panel-header>
        <nav class="flex min-w-0 flex-wrap gap-1 text-xs text-slate-500 dark:text-slate-400" data-requirement-path>
            @foreach ($requirement->ancestors() as $step)
                @if (! $loop->first)<span>›</span>@endif
                <button type="button" wire:click="selectNode({{ $step->number }})" class="hover:underline" title="{{ $step->title }}">{{ \Illuminate\Support\Str::limit($step->title, 12) }}</button>
            @endforeach
        </nav>
        <div x-data="{ open: false }" class="mt-2 flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <h2 :class="open ? '' : 'line-clamp-2'" class="text-base font-semibold text-slate-950 dark:text-white">{{ $this->mentions->html($requirement->title) }}</h2>
                @if (mb_strlen($requirement->title) > 60)
                    <button type="button" x-on:click="open = ! open" x-text="open ? '收起' : '展开'" class="text-xs text-slate-500 hover:underline">展开</button>
                @endif
            </div>
            @include('filament.pages.partials.requirement-progress', ['requirement' => $requirement, 'progress' => $this->progressOf($requirement)])
        </div>
    </header>

    @if ($this->isDecidable($requirement))
        {{-- The card: the question, now vs. the new requirement with where each comes from, a warning, the answers. A pick is a draft until 确认这一批. --}}
        @php
            $decision = $requirement->decisionOrFallback();
            $draft = $this->drafts->get($requirement->id);
            $isCustom = $draft?->choice === \App\Models\RequirementDecisionDraft::CUSTOM;
            $newSource = \App\Models\Requirement::briefSource($requirement->source);
        @endphp

        <section class="@container mt-3" data-decide>
            @if ($decision->difference)<p class="text-sm text-slate-900 dark:text-white" data-question><span class="text-slate-500">要定的事：</span>{{ $this->mentions->html($decision->difference) }}</p>@endif

            {{-- Side by side only when each column still holds ~18 characters a line. --}}
            <div class="mt-3 grid gap-2 @lg:grid-cols-2">
                <div class="rounded-md border border-slate-200 p-2 dark:border-white/10" data-now>
                    <p class="text-xs text-slate-500">现在</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $this->mentions->html($decision->now) }}</p>
                    <p class="mt-1 text-xs text-slate-500" data-now-source>出处：{{ $this->mentions->html($requirement->nowSource() ?? '没有文档，是代码现状') }}</p>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 p-2 dark:border-amber-500/30 dark:bg-amber-500/10" data-change>
                    <p class="text-xs text-amber-700 dark:text-amber-300">新要求</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $this->mentions->html($decision->change) }}</p>
                    @if ($newSource)<p class="mt-1 text-xs text-slate-500" data-change-source>出处：{{ $this->mentions->html($newSource) }}</p>@endif
                </div>
            </div>

            @if ($decision->risk)<p class="mt-2 text-sm text-slate-700 dark:text-slate-300" data-risk><span class="text-slate-500">注意：</span>{{ $this->mentions->html($decision->risk) }}</p>@endif

            <div class="mt-3 space-y-1.5" data-options>
                @foreach ($decision->choices() as $option)
                    @php($isPicked = $draft?->choice === $option->key)
                    <label wire:key="option-{{ $requirement->id }}-{{ $option->key }}" class="flex cursor-pointer items-start gap-2 rounded-md border px-2 py-1.5 text-sm {{ $isPicked ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5' }}" @if ($isPicked) data-picked @endif>
                        <input type="radio" name="choice-{{ $requirement->id }}" value="{{ $option->key }}" @checked($isPicked) wire:click="choose({{ $requirement->id }}, @js($option->key))" class="mt-1">
                        <span class="min-w-0 flex-1">
                            <span class="text-slate-900 dark:text-white">{{ $this->mentions->html($option->label) }}</span><span class="text-slate-500 dark:text-slate-400">：{{ $this->mentions->html($option->consequence) }}</span>
                        </span>
                        @if ($option->recommended)<span class="shrink-0 whitespace-nowrap text-xs text-amber-600 dark:text-amber-400">★推荐</span>@endif
                    </label>
                @endforeach
                <div x-data="{ writing: @js($isCustom) }">
                    <button type="button" x-on:click="writing = ! writing" class="text-xs {{ $isCustom ? 'font-medium text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">✎ 自己写</button>
                    @if ($draft)<button type="button" wire:click="clearChoice({{ $requirement->id }})" class="ml-3 text-xs text-slate-400 hover:underline">清除选择</button>@endif
                    <textarea x-show="writing" x-cloak rows="2" placeholder="写了就按这句定下来" wire:change="writeCustom({{ $requirement->id }}, $event.target.value)"
                        class="mt-1 block w-full rounded-md border p-2 text-sm dark:bg-white/5 {{ $isCustom ? 'border-primary-500' : 'border-slate-200 dark:border-white/10' }}">{{ $isCustom ? $draft->custom_text : '' }}</textarea>
                </div>
            </div>
        </section>
    @else
        <section class="mt-3 space-y-1.5 text-sm text-slate-700 dark:text-slate-300" data-panel-why>
            @if ($replacement = $requirement->status === \App\Enums\RequirementStatus::Void ? $requirement->supersededBy : null)
                <p class="text-xs text-slate-500" data-superseded-by>被<button type="button" wire:click="selectNode({{ $replacement->number }})" class="mx-0.5 text-primary-600 hover:underline dark:text-primary-400">「{{ \Illuminate\Support\Str::limit($replacement->title, 30, '…') }}」</button>取代（{{ $replacement->decided_at?->toDateString() }}）</p>
            @endif
            @if ($requirement->rationale)<p class="whitespace-pre-wrap">{{ $this->mentions->html($requirement->rationale) }}</p>@endif
            <p class="text-xs text-slate-500" data-source>出处：{{ $this->mentions->html($source) }}</p>
            @if ($requirement->status === \App\Enums\RequirementStatus::Later)
                <div class="flex items-start gap-2 text-xs text-slate-500" data-later>
                    <p class="min-w-0 flex-1">这期不做，将来要做。什么时候再看：{{ $this->mentions->html($requirement->source ?: $requirement->rationale ?: '未记录') }}</p>
                    <button type="button" wire:click="startNow" class="shrink-0 rounded border border-slate-300 px-2 py-0.5 text-slate-700 hover:bg-slate-50 dark:border-white/20 dark:text-slate-200 dark:hover:bg-white/5">现在要做了</button>
                </div>
            @endif
            @php($isAccepting = $this->progressOf($requirement) === \App\Data\Requirements\RequirementProgress::AwaitingAcceptance)
            <details data-panel-progress @if ($isAccepting) open @endif>
                <summary class="cursor-pointer text-xs text-slate-500">做到哪了：{{ $this->builtSummary($requirement) }}</summary>
                @if ($isAccepting)
                    <button type="button" wire:click="acceptRule" class="mt-1 rounded border border-violet-300 px-2 py-0.5 text-xs text-violet-700 hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10" data-accept-rule>验收通过</button>
                @endif
                @if ($requirement->tests->isNotEmpty())
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($requirement->tests as $test)
                            <a href="{{ $this->testUrl($test) }}" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:underline dark:bg-white/10 dark:text-slate-300" title="T{{ $test->number }}">{{ \Illuminate\Support\Str::limit($test->title, 30) }} · {{ $test->last_result->value }}</a>
                        @endforeach
                    </div>
                @endif
            </details>
            @foreach (['依赖' => $requirement->dependsOn, '被依赖' => $requirement->dependents] as $label => $related)
                @if ($related->isNotEmpty())
                    <p class="text-xs text-slate-500" data-requirement-dependencies="{{ $label }}">{{ $label }}：@foreach ($related->sortBy('number') as $other)@if (! $loop->first)、@endif<button type="button" wire:click="selectNode({{ $other->number }})" class="text-slate-700 hover:underline dark:text-slate-300">{{ $other->title }}</button>@endforeach</p>
                @endif
            @endforeach
        </section>
    @endif

    @if ($requirement->flowchart !== null)
        <section class="mt-4" data-rule-flowchart>
            <h3 class="text-xs font-medium text-slate-500">流程图</h3>
            @include('filament.pages.partials.flowchart', ['flowchart' => $requirement->flowchart])
        </section>
    @endif

    <div class="mt-4 space-y-2">
        @unless ($timeline->isEmpty())
            <details data-timeline>
                <summary class="cursor-pointer text-xs font-medium text-slate-500">更早的说法（{{ count($timeline->entries) }}）</summary>
                <ol class="mt-2 space-y-2 border-l border-slate-200 pl-3 dark:border-white/10">
                    @foreach ($timeline->entries as $entry)
                        <li wire:key="timeline-{{ $loop->index }}" class="relative text-sm {{ $entry->related ? 'opacity-70' : '' }}" data-timeline-entry>
                            <span class="absolute -left-[17px] top-1.5 size-2 {{ $entry->isCode() ? 'rounded-sm bg-violet-500' : ($entry->current ? 'rounded-full bg-emerald-500' : 'rounded-full bg-slate-300 dark:bg-white/30') }}"></span>
                            <p class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="tabular-nums">{{ $entry->date ?? '现在' }}</span>
                                <span class="rounded px-1 {{ $entry->badgeClasses() }}">{{ $entry->whoLabel() }}</span>
                                @if ($entry->where)<span>{{ $this->mentions->html($entry->where) }}</span>@endif
                                @if ($entry->current)<span class="rounded bg-emerald-100 px-1 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">现在生效</span>@endif
                                @if ($entry->related)<span>（相关）</span>@endif
                            </p>
                            <p class="mt-0.5 text-slate-800 dark:text-slate-200">{{ $this->mentions->html($entry->said) }}</p>
                            @if ($entry->conflictWith && ! $entry->isCode())
                                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">⚠ 和「{{ $this->mentions->html($entry->conflictWith) }}」冲突</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($timeline->codeDisagrees())
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400" data-code-disagrees>⚠ 代码和现行说法不一致</p>
                @endif
            </details>
        @endunless
        <details data-requirement-history>
            <summary class="cursor-pointer text-xs font-medium text-slate-500">历史</summary>
            @include('filament.pages.partials.requirement-history', ['revisions' => $requirement->revisions, 'commits' => $requirement->recentCommits()])
        </details>
    </div>
</div>
