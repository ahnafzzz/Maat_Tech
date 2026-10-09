@props(['product', 'showcase'])

@php
    $gallery = collect($product->galleryImageUrls());
    $productImage = $product->primaryImageUrl();
    $videoUrl = $product->videoUrl();
@endphp

<section
    class="product-showroom min-w-0 max-w-full"
    data-product-showroom
    data-showroom-manifest="{{ asset($showcase['manifest']) }}"
    data-showroom-model="{{ $showcase['model_id'] }}"
    data-showroom-state="loading"
    data-showroom-animation="paused"
    data-product-media-gallery
    data-active-media="3d"
    aria-labelledby="showroom-heading"
>
    <div class="relative aspect-[4/3] overflow-hidden rounded-sm border border-slate-500/80 bg-[radial-gradient(circle_at_62%_28%,#9ca3aa_0%,#747d86_52%,#555e68_100%)]">
        <img
            src="{{ asset($showcase['poster']) }}"
            data-showroom-poster
            data-fallback-src="{{ $productImage }}"
            alt="{{ $product->name }} 3D preview"
            class="pointer-events-none absolute inset-0 h-full w-full object-cover transition-opacity duration-500 motion-reduce:transition-none"
            fetchpriority="high"
        >
        <canvas
            data-showroom-canvas
            tabindex="0"
            aria-label="Interactive 3D view of {{ $product->name }}. Drag horizontally to rotate. Focus and use arrow keys to rotate, plus and minus to zoom, and Home to reset."
            class="absolute inset-0 h-full w-full touch-pan-y opacity-0 outline-none transition-opacity duration-500 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300 motion-reduce:transition-none"
        ></canvas>
        @foreach ($gallery as $image)
            <div hidden data-media-panel="photo-{{ $loop->index }}" class="absolute inset-0 grid place-items-center bg-[#f2efe9] p-3 sm:p-6">
                <img data-media-image data-src="{{ $image }}" alt="{{ $product->name }} photograph {{ $loop->iteration }}" class="h-full w-full object-contain object-center">
                <div hidden data-media-fallback class="absolute inset-0 grid place-items-center bg-[#0c1119] text-center text-sm text-slate-400">This photograph could not be loaded.</div>
            </div>
        @endforeach
        @if ($videoUrl)
            <div hidden data-media-panel="video" class="absolute inset-0 grid place-items-center bg-black p-2 sm:p-4">
                <video data-media-video data-src="{{ $videoUrl }}" controls preload="none" playsinline class="h-full w-full object-contain" aria-label="{{ $product->name }} product video"></video>
                <div hidden data-media-fallback class="absolute inset-0 grid place-items-center bg-[#0c1119] text-center text-sm text-slate-400">This video could not be loaded.</div>
            </div>
        @endif
        <div data-showroom-labels class="pointer-events-none absolute inset-0" aria-label="Visible model part labels"></div>
        <div data-showroom-only class="pointer-events-none absolute inset-x-4 top-4 flex min-w-0 flex-col items-start gap-1 font-mono text-[10px] uppercase tracking-[.14em] text-slate-700 sm:flex-row sm:justify-between sm:gap-3">
            <h2 id="showroom-heading">{{ $product->name }} · interactive view</h2>
            <span data-showroom-status class="max-w-full text-right" aria-live="polite">Loading interactive 3D view</span>
        </div>
        <div data-showroom-only class="absolute bottom-4 right-4 flex gap-2">
            <button type="button" disabled data-showroom-controls data-showroom-action="zoom" data-showroom-value="0.84" aria-label="Zoom in" class="showroom-button"><i data-lucide="zoom-in" class="h-4 w-4"></i></button>
            <button type="button" disabled data-showroom-controls data-showroom-action="zoom" data-showroom-value="1.19" aria-label="Zoom out" class="showroom-button"><i data-lucide="zoom-out" class="h-4 w-4"></i></button>
            <button type="button" disabled data-showroom-controls data-showroom-action="reset" class="showroom-button gap-2 px-3" aria-label="Reset 3D view"><i data-lucide="rotate-ccw" class="h-4 w-4"></i><span class="hidden sm:inline">Reset View</span></button>
        </div>
        <button type="button" data-showroom-action="retry" class="showroom-retry absolute bottom-4 left-4 hidden min-h-11 items-center border border-white/80 bg-slate-900/90 px-4 py-2 text-sm font-semibold text-white">Retry 3D</button>
    </div>

    <div class="mt-3 flex gap-2 overflow-x-auto pb-2" role="tablist" aria-label="Product media">
        <button type="button" role="tab" aria-selected="true" data-media-select="3d" class="media-thumbnail shrink-0 border-tech-400 bg-tech-950/50 text-tech-200"><span class="grid h-14 w-16 place-items-center font-mono text-sm font-bold">3D</span><span class="sr-only">Interactive 3D model</span></button>
        @foreach ($gallery as $image)
            <button type="button" role="tab" aria-selected="false" data-media-select="photo-{{ $loop->index }}" class="media-thumbnail shrink-0 border-cyber-border bg-[#0c1119]">
                <img src="{{ $image }}" alt="" class="h-14 w-16 object-cover" loading="lazy">
                <span class="sr-only">Product photograph {{ $loop->iteration }}</span>
            </button>
        @endforeach
        @if ($videoUrl)
            <button type="button" role="tab" aria-selected="false" data-media-select="video" class="media-thumbnail relative shrink-0 border-cyber-border bg-[#0c1119] text-white">
                <span class="grid h-14 w-16 place-items-center"><i data-lucide="play" class="h-6 w-6"></i></span><span class="sr-only">Product video</span>
            </button>
        @endif
    </div>

    <div data-showroom-only>
    <p class="mt-2 text-sm leading-6 text-slate-400">Drag to rotate; hold Shift while dragging to pan. Pinch to zoom on touch screens. Wheel zoom works while the viewer is focused.</p>

    <div class="mt-5 grid gap-4 rounded-sm border border-cyber-border bg-[#0d121b] p-4 sm:grid-cols-2">
        <fieldset disabled data-showroom-controls class="min-w-0">
            <legend class="font-mono text-[10px] uppercase tracking-[.16em] text-slate-500">Color</legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <button type="button" data-showroom-action="finish" data-showroom-value="black" aria-pressed="true" class="showroom-choice">Black</button>
                <button type="button" data-showroom-action="finish" data-showroom-value="white" aria-pressed="false" class="showroom-choice">White</button>
            </div>
        </fieldset>
        <fieldset disabled data-showroom-controls class="min-w-0">
            <legend class="font-mono text-[10px] uppercase tracking-[.16em] text-slate-500">Light</legend>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button type="button" data-showroom-action="light" data-showroom-value="warm" aria-pressed="false" class="showroom-choice">Warm</button>
                <button type="button" data-showroom-action="light" data-showroom-value="neutral" aria-pressed="false" class="showroom-choice">Neutral</button>
                <button type="button" data-showroom-action="light" data-showroom-value="cool" aria-pressed="true" class="showroom-choice">White</button>
                <button type="button" data-showroom-action="light" data-showroom-value="off" aria-pressed="false" class="showroom-choice">Off</button>
            </div>
        </fieldset>
        <fieldset disabled data-showroom-controls class="min-w-0 sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-300">Power mode: <output data-showroom-output="powerMode">10 / 10</output>
                <input type="range" min="1" max="10" step="1" value="10" data-default-value="10" data-showroom-input="powerMode" class="mt-2 min-h-11 w-full accent-teal-400">
            </label>
            <div class="grid grid-cols-10 text-center font-mono text-[9px] text-slate-500" aria-hidden="true">
                @foreach (range(1, 10) as $level)<span>{{ $level }}</span>@endforeach
            </div>
        </fieldset>
    </div>

    <details id="engineering-view" class="mt-4 border border-cyber-border bg-[#0b1018]">
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold text-white outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300">
            <span>Explore Engineering View</span><span class="inline-flex min-h-11 items-center gap-2 text-sm text-tech-300">Expand controls<i data-lucide="chevron-down" class="h-5 w-5 shrink-0"></i></span>
        </summary>
        <div class="border-t border-cyber-border p-4">
            <p class="text-sm leading-6 text-slate-400">Move the documented joints within the supplied model’s limits. These controls demonstrate geometry and are not product specifications.</p>
            <fieldset disabled data-showroom-controls class="mt-5">
                <legend class="font-mono text-[10px] uppercase tracking-[.16em] text-slate-500">Camera and inspection</legend>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button type="button" data-showroom-action="camera" data-showroom-value="perspective" class="showroom-choice">Perspective</button>
                    <button type="button" data-showroom-action="camera" data-showroom-value="front" class="showroom-choice">Front</button>
                    <button type="button" data-showroom-action="fit" class="showroom-choice">Fit all</button>
                    <button type="button" data-showroom-action="inspect" data-showroom-value="controller" class="showroom-choice">Controller</button>
                    <button type="button" data-showroom-action="inspect" data-showroom-value="clamp" class="showroom-choice">Clamp base</button>
                    <button type="button" data-showroom-action="inspect" data-showroom-value="head" class="showroom-choice">Light head</button>
                </div>
                <label class="mt-3 flex min-h-11 items-center gap-3 text-sm text-slate-300">
                    <input type="checkbox" checked data-default-value="true" data-showroom-input="autoRotate" aria-label="Stop automatic rotation" class="h-5 w-5 accent-teal-400">Auto rotate complete lamp
                </label>
            </fieldset>
            <fieldset disabled data-showroom-controls class="mt-5 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['lower', 'Lower arm', 58, 142, 118, 1, '118°'],
                    ['upper', 'Upper arm', -85, 112, 47, 1, '47°'],
                    ['tilt', 'Neck bend', -70, 95, 0, 1, '0°'],
                    ['roll', 'Head rotate', -100, 100, -25, 1, '-25°'],
                    ['baseYaw', 'Base swivel', -170, 170, 0, 1, '0°'],
                    ['jaw', 'Clamp opening', 4, 53.3, 25.1, .1, '25.1 mm'],
                ] as [$name, $label, $min, $max, $value, $step, $display])
                    <label class="text-xs text-slate-400">{{ $label }}: <output data-showroom-output="{{ $name }}">{{ $display }}</output>
                        <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" data-default-value="{{ $value }}" data-showroom-input="{{ $name }}" class="mt-2 min-h-11 w-full accent-teal-400">
                    </label>
                @endforeach
            </fieldset>
            <fieldset disabled data-showroom-controls class="mt-5">
                <legend class="font-mono text-[10px] uppercase tracking-[.16em] text-slate-500">Original pose presets</legend>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach (['study' => 'Study', 'reach' => 'Reach', 'tall' => 'Tall', 'low' => 'Low', 'wide' => 'Wide', 'folded' => 'Folded'] as $value => $label)
                        <button type="button" data-showroom-action="preset" data-showroom-value="{{ $value }}" class="showroom-choice">{{ $label }}</button>
                    @endforeach
                </div>
                <button type="button" data-showroom-action="resetPose" class="showroom-choice mt-2 w-full">Reset pose</button>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" data-showroom-action="explodeToggle" class="showroom-choice">Toggle exploded anatomy</button>
                    <button type="button" data-showroom-action="separateClamp" class="showroom-choice">Separate clamp components</button>
                </div>
                <label class="mt-4 block text-xs text-slate-400">Exploded separation: <output data-showroom-output="explode">0%</output>
                    <input type="range" min="0" max="100" value="0" data-default-value="0" data-showroom-input="explode" class="mt-2 min-h-11 w-full accent-teal-400">
                </label>
                <label class="mt-3 flex min-h-11 items-center gap-3 text-sm text-slate-300">
                    <input type="checkbox" data-default-value="false" data-showroom-input="anatomy" class="h-5 w-5 accent-teal-400">Show anatomy colours and available part labels
                </label>
                <label class="mt-3 flex min-h-11 items-center gap-3 text-sm text-slate-300">
                    <input type="checkbox" data-default-value="false" data-showroom-input="wires" class="h-5 w-5 accent-teal-400">Show external power leads for inspection
                </label>
            </fieldset>
            <fieldset disabled data-showroom-controls class="mt-5">
                <div class="max-w-xl">
                    <label class="font-mono text-[10px] uppercase tracking-[.16em] text-slate-500" for="showroom-part-select">Inspect part group</label>
                    <select id="showroom-part-select" data-default-value="all" data-showroom-input="selection" class="mt-2 min-h-11 w-full border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200 outline-none focus-visible:ring-2 focus-visible:ring-tech-300">
                        <option value="all">Complete lamp</option>
                        <option value="clamp">Clamp & screw</option>
                        <option value="lower">Lower linkage</option>
                        <option value="upper">Upper linkage</option>
                        <option value="head">Light head</option>
                        <option value="springs">Tension springs</option>
                        <option value="hardware">Pivot hardware</option>
                        <option value="controller">Light controller</option>
                        <option value="cable">Power leads</option>
                        <option value="usb">USB connector</option>
                    </select>
                    <p data-showroom-part-description class="mt-2 min-h-10 text-xs leading-5 text-slate-500">Complete articulated lamp assembly.</p>
                    <label class="mt-2 flex min-h-11 items-center gap-3 text-sm text-slate-300">
                        <input type="checkbox" data-default-value="false" data-showroom-input="isolate" class="h-5 w-5 accent-teal-400">Isolate selected group
                    </label>
                    <button type="button" data-showroom-action="focus" class="showroom-choice mt-2 w-full">Focus selected</button>
                </div>
            </fieldset>
        </div>
    </details>
    </div>
</section>
