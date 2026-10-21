<x-layouts::app :title="$project->name . ' review'">
    <main class="mx-auto w-full max-w-5xl space-y-6 p-6">
        <header class="space-y-2">
            <p class="text-sm text-zinc-500">{{ $project->name }} · Read-only</p>
            <h1 class="text-2xl font-semibold">{{ ucfirst($tree) }} ({{ $count }})</h1>
            <nav class="flex gap-4 text-sm" aria-label="Review pages">
                <a class="underline" href="{{ route('review.tree', ['project' => $project, 'tree' => 'requirements']) }}">Requirements</a>
                <a class="underline" href="{{ route('review.tree', ['project' => $project, 'tree' => 'tests']) }}">Tests</a>
            </nav>
        </header>
        <section class="space-y-3">
            @forelse ($nodes->get(0, collect()) as $node)
                @include('review.node', ['node' => $node, 'ancestors' => []])
            @empty
                <p class="text-zinc-500">No records yet.</p>
            @endforelse
        </section>
    </main>
</x-layouts::app>
