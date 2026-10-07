{{-- One radio group of a decision's options plus ✎ 自己写; $prefix makes the pick a decision (''), an opinion ('opinion:') or the boss's reply ('boss:'). --}}
@php
    $custom = $prefix.\App\Models\RequirementDecisionDraft::CUSTOM;
    $isCustom = $draft?->choice === $custom;
@endphp

<div class="space-y-1.5" data-options="{{ $prefix ?: 'decide' }}">
    @foreach ($decision->options as $option)
        @php($isPicked = $draft?->choice === $prefix.$option->key)
        <label wire:key="option-{{ $requirement->id }}-{{ $prefix }}{{ $option->key }}" class="flex cursor-pointer items-start gap-2 rounded-md border px-2 py-1.5 text-sm {{ $isPicked ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5' }}" @if ($isPicked) data-picked @endif>
            <input type="radio" name="choice-{{ $requirement->id }}-{{ $prefix }}" value="{{ $option->key }}" @checked($isPicked) wire:click="choose({{ $requirement->id }}, @js($prefix.$option->key))" class="mt-1">
            <span class="min-w-0">
                <span class="font-medium text-slate-900 dark:text-white">{{ $option->key }}. {{ $option->label }}</span>@if ($option->recommended)<span class="ml-1 whitespace-nowrap text-xs text-amber-600 dark:text-amber-400">★推荐</span>@endif
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $option->consequence }}@if ($option->resultTitle)（定了以后：{{ $option->resultTitle }}）@endif</span>
            </span>
        </label>
    @endforeach
    <div x-data="{ writing: @js($isCustom) }">
        <button type="button" x-on:click="writing = ! writing" class="text-xs {{ $isCustom ? 'font-medium text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">✎ 自己写</button>
        <textarea x-show="writing" x-cloak rows="2" placeholder="{{ $placeholder }}" wire:change="writeCustom({{ $requirement->id }}, $event.target.value, @js($custom))"
            class="mt-1 block w-full rounded-md border p-2 text-sm dark:bg-white/5 {{ $isCustom ? 'border-primary-500' : 'border-slate-200 dark:border-white/10' }}">{{ $isCustom ? $draft->custom_text : '' }}</textarea>
    </div>
</div>
