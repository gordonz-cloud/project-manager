@php($mermaid = \App\Support\FlowchartMermaid::fromFlowchart($feature->flowchart))
    <div
        x-data="{
            fullscreen: false,
            scale: 1, tx: 0, ty: 0, dragging: false, lastX: 0, lastY: 0,
            toggle() { document.fullscreenElement ? document.exitFullscreen() : $refs.chartWrap.requestFullscreen() },
            reset() { this.scale = 1; this.tx = 0; this.ty = 0 },
            onWheel(e) { if (!this.fullscreen) return; e.preventDefault(); this.scale = Math.min(8, Math.max(0.2, this.scale * (e.deltaY > 0 ? 0.9 : 1.1))) },
            onDown(e) { if (!this.fullscreen) return; this.dragging = true; this.lastX = e.clientX; this.lastY = e.clientY },
            onMove(e) { if (!this.dragging) return; this.tx += e.clientX - this.lastX; this.ty += e.clientY - this.lastY; this.lastX = e.clientX; this.lastY = e.clientY },
            onUp() { this.dragging = false },
        }"
        x-init="document.addEventListener('fullscreenchange', () => { fullscreen = document.fullscreenElement === $refs.chartWrap; if (! fullscreen) reset() })"
        x-ref="chartWrap"
        :class="fullscreen ? 'relative fixed inset-0 z-50 flex flex-col bg-white p-4 dark:bg-slate-950' : 'relative mt-4'"
    >
        <div class="mb-2 flex items-center justify-end" x-show="fullscreen" x-cloak>
            <span class="mr-auto text-xs font-medium text-slate-500 dark:text-slate-400">流程图</span>
        </div>
        <button
            type="button"
            @click="toggle()"
            aria-label="全屏查看流程图"
            data-flowchart-fullscreen-toggle
            :class="fullscreen ? 'absolute right-4 top-4' : 'absolute right-0 top-0'"
            class="z-10 rounded p-1 text-slate-400 hover:text-slate-900 dark:hover:text-white"
        >
            <x-filament::icon icon="heroicon-m-arrows-pointing-out" class="size-4" x-show="!fullscreen" />
            <x-filament::icon icon="heroicon-m-x-mark" class="size-4" x-show="fullscreen" x-cloak />
        </button>

        <div
            class="relative"
            :class="fullscreen ? 'flex-1 overflow-hidden' : 'max-h-[45vh] overflow-auto'"
            @wheel="onWheel"
            @mousedown="onDown"
            @mousemove.window="onMove"
            @mouseup.window="onUp"
        >
            <div
                wire:key="flowchart-{{ $feature->getKey() }}-{{ md5($mermaid) }}"
                wire:ignore
                data-flowchart
                data-source="{{ $mermaid }}"
                data-tooltips="{{ json_encode(\App\Support\FlowchartMermaid::tooltips($feature->flowchart)) }}"
                :style="fullscreen ? `transform: translate(${tx}px, ${ty}px) scale(${scale}); transform-origin: center center; height: 100%; display: flex; align-items: center; justify-content: center;` : ''"
                class="[&_svg]:max-w-full [&_svg]:max-h-full"
                x-init="
                    const draw = async () => {
                        $el.innerHTML = (await window.mermaid.render('flowchart-' + Date.now(), $el.dataset.source)).svg;
                        const tooltips = JSON.parse($el.dataset.tooltips || '{}');
                        $el.querySelectorAll('g.node').forEach((node) => {
                            const tip = tooltips[node.dataset.id ?? (node.id.match(/-(n\d+)-\d+$/) || [])[1]];
                            if (tip) { const title = document.createElementNS('http://www.w3.org/2000/svg', 'title'); title.textContent = tip; node.prepend(title) }
                        });
                    };
                    if (window.mermaid) { draw() } else { const id = setInterval(() => { if (window.mermaid) { clearInterval(id); draw() } }, 30) }
                "
            ></div>
        </div>
    </div>
    @if (filled($feature->flowchart->pseudocode))
        <pre class="mt-4 overflow-x-auto whitespace-pre-wrap rounded-md bg-slate-50 p-3 font-mono text-xs leading-5 text-slate-700 dark:bg-white/5 dark:text-slate-300" data-pseudocode>{{ $feature->flowchart->pseudocode }}</pre>
    @endif
