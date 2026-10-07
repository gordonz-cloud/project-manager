{{-- 「需要你决定」: only what the decision needs; the panel around it carries the path, title, status, why and links. --}}
@php
    $decision = $requirement->decisionOrFallback();
    $draft = $this->drafts->get($requirement->id);
    $old = $requirement->supersedes;
    $isBoss = $requirement->decider === \App\Enums\RequirementDecider::Boss;
    $isCustom = $draft?->choice === \App\Models\RequirementDecisionDraft::CUSTOM;
    $action = fn (bool $isOn): string => $isOn ? 'font-medium text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
@endphp

<section class="mt-4 rounded-lg border border-amber-200 p-3 dark:border-amber-500/30" data-decide>
    <h3 class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $isBoss ? '老板的答复（确认后记成老板拍板）' : '需要你决定' }}</h3>

    <div class="mt-2 grid gap-2 md:grid-cols-2">
        <div class="rounded-md bg-slate-50 p-2 dark:bg-white/5" data-now>
            <p class="text-xs text-slate-500">现在</p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $decision->now }}</p>
            @if ($old && $decision->now !== $old->title)<p class="mt-1 text-xs text-slate-500">现行规则：{{ $old->title }}</p>@endif
        </div>
        <div class="rounded-md bg-amber-50 p-2 dark:bg-amber-500/10" data-change>
            <p class="text-xs text-amber-700 dark:text-amber-300">要改成</p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $decision->change }}</p>
        </div>
    </div>

    @if ($decision->difference)<p class="mt-2 text-sm text-slate-700 dark:text-slate-300"><span class="mr-2 text-xs text-slate-500">差别</span>{{ $decision->difference }}</p>@endif
    @if ($decision->risk)<p class="mt-1 text-sm text-slate-700 dark:text-slate-300"><span class="mr-2 text-xs text-slate-500">风险</span>{{ $decision->risk }}</p>@endif

    <div class="mt-3 space-y-1.5">
        @foreach ($decision->options as $option)
            @php($isPicked = $draft?->choice === $option->key)
            <label wire:key="option-{{ $requirement->id }}-{{ $option->key }}" class="flex cursor-pointer items-start gap-2 rounded-md border px-2 py-1.5 text-sm {{ $isPicked ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5' }}" @if ($isPicked) data-picked @endif>
                <input type="radio" name="choice-{{ $requirement->id }}" value="{{ $option->key }}" @checked($isPicked) wire:click="choose({{ $requirement->id }}, @js($option->key))" class="mt-1">
                <span class="min-w-0">
                    <span class="font-medium text-slate-900 dark:text-white">{{ $option->key }}. {{ $option->label }}</span>@if ($option->recommended)<span class="ml-1 whitespace-nowrap text-xs text-amber-600 dark:text-amber-400">★推荐</span>@endif
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $option->consequence }}@if ($option->resultTitle)（定了以后：{{ $option->resultTitle }}）@endif</span>
                </span>
            </label>
        @endforeach
    </div>

    <div x-data="{ writing: @js($isCustom) }" class="mt-2">
        <div class="flex flex-wrap items-center gap-3 text-xs">
            <button type="button" x-on:click="writing = ! writing" class="{{ $action($isCustom) }}">✎ 自己写</button>
            @unless ($isBoss)
                <button type="button" wire:click="choose({{ $requirement->id }}, 'ask_boss')" class="{{ $action($draft?->choice === 'ask_boss') }}">问老板</button>
            @endunless
            <button type="button" wire:click="choose({{ $requirement->id }}, 'skip')" class="{{ $action($draft?->choice === 'skip') }}">先不定</button>
            @if ($draft)<button type="button" wire:click="clearChoice({{ $requirement->id }})" class="ml-auto text-slate-400 hover:underline">清除选择</button>@endif
        </div>
        <textarea x-show="writing" x-cloak rows="2" placeholder="写了就按这句定下来" wire:change="writeCustom({{ $requirement->id }}, $event.target.value)"
            class="mt-2 block w-full rounded-md border p-2 text-sm dark:bg-white/5 {{ $isCustom ? 'border-primary-500' : 'border-slate-200 dark:border-white/10' }}">{{ $draft?->custom_text }}</textarea>
    </div>
</section>
