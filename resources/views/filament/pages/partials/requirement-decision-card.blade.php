@php
    $decision = $requirement->decisionOrFallback();
    $draft = $this->drafts->get($requirement->id);
    $old = $requirement->supersedes;
    $isConflict = $requirement->status === \App\Enums\RequirementStatus::Conflict;
    $isBoss = $requirement->decider === \App\Enums\RequirementDecider::Boss;
    $pickedLabel = match ($draft?->choice) {
        null => null,
        \App\Models\RequirementDecisionDraft::CUSTOM => '自己写',
        \App\Models\RequirementDecisionDraft::ASK_BOSS => '问老板',
        \App\Models\RequirementDecisionDraft::SKIP => '先不定',
        default => $draft->choice,
    };
    $chip = 'rounded-md border px-2 py-1 text-xs';
    $chipOn = 'border-primary-600 bg-primary-600 text-white';
    $chipOff = 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5';
@endphp

<article wire:key="decision-{{ $requirement->id }}" data-decision-card="{{ $requirement->number }}" class="rounded-lg border p-4 {{ $draft ? 'border-primary-400 dark:border-primary-500/60' : 'border-slate-200 dark:border-white/10' }}">
    <p class="flex flex-wrap items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
        <span class="rounded px-1 {{ $isConflict ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' }}">{{ $isConflict ? '要改一条已定规则' : '新提议' }}</span>
        @if ($isBoss)<span class="rounded bg-slate-100 px-1 dark:bg-white/10">等老板</span>@endif
        <span>{{ implode(' › ', $this->pathOf($requirement)) }}</span>
        <button type="button" wire:click="selectNode({{ $requirement->number }})" class="ml-auto hover:underline">#{{ $requirement->number }}</button>
    </p>

    <h3 class="mt-2 flex items-start gap-2 text-base font-semibold text-slate-950 dark:text-white">
        <span class="flex-1">{{ $requirement->title }}</span>
        @if ($pickedLabel)<span class="shrink-0 rounded bg-primary-50 px-1.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300" data-picked>已选 {{ $pickedLabel }}</span>@endif
    </h3>

    <div class="mt-3 grid gap-3 md:grid-cols-2">
        <div class="rounded-md bg-slate-50 p-3 dark:bg-white/5" data-now>
            <p class="text-xs font-medium text-slate-500">现在</p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $decision->now }}</p>
            @if ($old && $decision->now !== $old->title)
                <p class="mt-2 text-xs text-slate-500">现行规则：{{ $old->title }}</p>
            @endif
        </div>
        <div class="rounded-md bg-amber-50 p-3 dark:bg-amber-500/10" data-change>
            <p class="text-xs font-medium text-amber-700 dark:text-amber-300">要改成</p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $decision->change }}</p>
        </div>
    </div>

    <dl class="mt-3 space-y-1 text-sm text-slate-700 dark:text-slate-300">
        @if ($decision->difference)<div class="flex gap-2"><dt class="shrink-0 text-xs leading-5 text-slate-500">差别</dt><dd>{{ $decision->difference }}</dd></div>@endif
        @if ($decision->risk)<div class="flex gap-2"><dt class="shrink-0 text-xs leading-5 text-slate-500">风险</dt><dd>{{ $decision->risk }}</dd></div>@endif
    </dl>

    <fieldset class="mt-3 space-y-2">
        <legend class="text-xs text-slate-500">{{ $isBoss ? '老板的答复（确认后记成老板拍板）' : '你的决定' }}</legend>
        @foreach ($decision->options as $option)
            <label class="flex cursor-pointer items-start gap-2 rounded-md border p-2 text-sm {{ $draft?->choice === $option->key ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5' }}">
                <input type="radio" name="choice-{{ $requirement->id }}" value="{{ $option->key }}" @checked($draft?->choice === $option->key) wire:click="choose({{ $requirement->id }}, @js($option->key))" class="mt-1">
                <span class="min-w-0">
                    <span class="font-medium text-slate-900 dark:text-white">{{ $option->key }}. {{ $option->label }}</span>
                    @if ($option->recommended)<span class="ml-1 text-xs font-medium text-amber-600 dark:text-amber-400">★推荐</span>@endif
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $option->consequence }}</span>
                    @if ($option->resultTitle)<span class="block text-xs text-slate-500 dark:text-slate-400">定了以后：{{ $option->resultTitle }}</span>@endif
                </span>
            </label>
        @endforeach
        <textarea
            rows="2"
            placeholder="自己写…（写了就按这句定下来）"
            wire:change="writeCustom({{ $requirement->id }}, $event.target.value)"
            class="block w-full rounded-md border p-2 text-sm dark:bg-white/5 {{ $draft?->choice === \App\Models\RequirementDecisionDraft::CUSTOM ? 'border-primary-500' : 'border-slate-200 dark:border-white/10' }}"
        >{{ $draft?->custom_text }}</textarea>
    </fieldset>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        @unless ($isBoss)
            <button type="button" wire:click="choose({{ $requirement->id }}, 'ask_boss')" class="{{ $chip }} {{ $draft?->choice === 'ask_boss' ? $chipOn : $chipOff }}">问老板</button>
        @endunless
        <button type="button" wire:click="choose({{ $requirement->id }}, 'skip')" class="{{ $chip }} {{ $draft?->choice === 'skip' ? $chipOn : $chipOff }}">先不定</button>
        @if ($draft)
            <button type="button" wire:click="clearChoice({{ $requirement->id }})" class="text-xs text-slate-500 hover:underline">清除选择</button>
        @endif
    </div>

    <div class="mt-3 border-t border-slate-100 pt-2 dark:border-white/5">
        <p class="mb-1 text-xs text-slate-500">会动到{{ $decision->impact ? '：'.$decision->impact : '' }}</p>
        @include('filament.pages.partials.requirement-links', [
            'features' => $requirement->linkedFeatures->merge($old?->linkedFeatures ?? [])->unique('id'),
            'tests' => $requirement->tests->merge($old?->tests ?? [])->unique('id'),
        ])
        @if ($requirement->rationale || $requirement->source)
            <details class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                <summary class="cursor-pointer">背景和出处</summary>
                @if ($requirement->rationale)<p class="mt-1 whitespace-pre-wrap">{{ $requirement->rationale }}</p>@endif
                @if ($requirement->source)<p class="mt-1">出处：{{ $requirement->source }}</p>@endif
            </details>
        @endif
    </div>
</article>
