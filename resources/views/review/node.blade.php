@if (! in_array($node->id, $ancestors, true))
    <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" id="node-{{ $node->number }}">
        <summary class="cursor-pointer text-sm font-medium">
            {{ $tree === 'requirements' ? 'R' : 'T' }}{{ $node->number }} · {{ $node->title }}
            <span class="text-xs text-zinc-500">{{ $node->status->value }}@if ($tree === 'tests') · {{ $node->last_result->value }}@endif</span>
        </summary>
        <div class="mt-3 space-y-3 text-sm">
            @if ($tree === 'requirements')
                <p class="whitespace-pre-wrap">{{ $node->acceptance }}</p>
                <p class="whitespace-pre-wrap text-zinc-500">{{ $node->rationale }}</p>
                <p class="text-xs text-zinc-500">{{ $node->source }}</p>
            @else
                <p class="whitespace-pre-wrap">{{ $node->expected }}</p>
                <p class="break-all font-mono text-xs text-zinc-500">{{ $node->location }} {{ $node->test_name }}</p>
                <p class="whitespace-pre-wrap text-zinc-500">{{ $node->notes }}</p>
            @endif
            @foreach ($nodes->get($node->id, collect()) as $child)
                @include('review.node', ['node' => $child, 'ancestors' => [...$ancestors, $node->id]])
            @endforeach
        </div>
    </details>
@endif
