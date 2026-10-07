{{-- 「需要你决定」: only what the decision needs; the panel around it carries the path, title, status, why and links. --}}
@php
    $decision = $requirement->decisionOrFallback();
    $draft = $this->drafts->get($requirement->id);
    $old = $requirement->supersedes;
    $isBoss = $requirement->decider === \App\Enums\RequirementDecider::Boss;
    $action = fn (bool $isOn): string => $isOn ? 'font-medium text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
@endphp

<section class="@container mt-4 rounded-lg border border-amber-200 p-3 dark:border-amber-500/30" data-decide>
    <h3 class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $isBoss ? '老板的问题' : '需要你决定' }}</h3>

    {{-- Side by side only when each column still holds ~18 characters a line. --}}
    <div class="mt-2 grid gap-2 @lg:grid-cols-2">
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

    @if ($isBoss)
        @if ($decision->whyBoss)<p class="mt-1 text-sm text-slate-700 dark:text-slate-300"><span class="mr-2 text-xs text-slate-500">为什么要老板定</span>{{ $decision->whyBoss }}</p>@endif
        @if ($opinion = $requirement->decision_opinion)
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-300" data-opinion>你的意见：{{ $opinion['key'] === 'custom' ? $opinion['label'] : "{$opinion['key']}. {$opinion['label']}" }}（{{ $requirement->sent_to_boss_at ? '已发老板 '.$requirement->sent_to_boss_at->toDateString() : '待发老板' }}）</p>
        @endif
        <h4 class="mt-3 mb-1.5 text-xs text-slate-500">你的意见（会带进问老板清单）</h4>
        @include('filament.pages.partials.requirement-decide-options', ['prefix' => \App\Models\RequirementDecisionDraft::OPINION, 'placeholder' => '你的意见，会带进问老板清单'])
        <div x-data="{ open: @js((bool) $draft?->isBossReply()) }" class="mt-3 border-t border-amber-100 pt-2 dark:border-amber-500/20">
            <button type="button" x-on:click="open = ! open" class="text-xs font-medium text-slate-600 hover:underline dark:text-slate-300">记老板的回复</button>
            <div x-show="open" x-cloak class="mt-2" data-boss-reply>
                @include('filament.pages.partials.requirement-decide-options', ['prefix' => \App\Models\RequirementDecisionDraft::BOSS, 'placeholder' => '老板的原话，确认后按这句定下来'])
            </div>
        </div>
    @else
        <div class="mt-3">
            @include('filament.pages.partials.requirement-decide-options', ['prefix' => '', 'placeholder' => '写了就按这句定下来'])
        </div>
    @endif

    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
        @unless ($isBoss)
            <button type="button" wire:click="choose({{ $requirement->id }}, 'ask_boss')" class="{{ $action($draft?->choice === 'ask_boss') }}">问老板</button>
        @endunless
        <button type="button" wire:click="choose({{ $requirement->id }}, 'skip')" class="{{ $action($draft?->choice === 'skip') }}">先不定</button>
        @if ($draft)<button type="button" wire:click="clearChoice({{ $requirement->id }})" class="ml-auto text-slate-400 hover:underline">清除选择</button>@endif
    </div>
</section>
