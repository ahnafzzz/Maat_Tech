@props(['slides'])

@php($slideCount = $slides->count())
<section
    class="storefront-slideshow relative w-full overflow-hidden border-b border-cyber-border bg-[#080b10]"
    data-storefront-slideshow
    data-slideshow-interval="3000"
    data-slideshow-index="0"
    data-slideshow-animation="paused"
    aria-roledescription="carousel"
    aria-label="Storefront highlights"
>
    <div class="relative aspect-video">
        @foreach($slides as $slide)
            <article
                class="absolute inset-x-0 bottom-14 top-0 transition-opacity duration-500 motion-reduce:transition-none"
                data-slideshow-slide
                data-active="{{ $loop->first ? 'true' : 'false' }}"
                aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                aria-label="Slide {{ $loop->iteration }} of {{ $slideCount }}"
            >
                <a href="{{ $slide->href() }}" class="grid h-full w-full overflow-hidden bg-[#0b1016] outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300 {{ $slide->layout === 'split' ? 'grid-cols-[.44fr_.56fr] md:grid-cols-[minmax(18rem,.85fr)_minmax(20rem,1.15fr)]' : 'place-items-center' }}" @if(!$loop->first) tabindex="-1" @endif>
                    @if($slide->layout === 'split')
                        <div class="grid h-full min-h-0 place-items-center overflow-hidden bg-black/20">
                            <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->alt_text }}" class="h-full max-h-full w-full object-contain object-center" @if(!$loop->first) loading="lazy" @endif>
                        </div>
                        <div class="flex items-center border-l border-cyber-border bg-[radial-gradient(circle_at_20%_20%,rgba(20,184,166,.12),transparent_58%)] p-3 sm:p-6 md:p-10 lg:p-14">
                            <div class="max-w-xl">
                                @if($slide->eyebrow)<p class="font-mono text-[10px] uppercase tracking-[.2em] text-tech-400">{{ $slide->eyebrow }}</p>@endif
                                <h2 class="mt-1 text-sm font-bold leading-tight text-white sm:mt-3 sm:text-2xl lg:text-4xl">{{ $slide->title }}</h2>
                                @if($slide->subtitle)<p class="mt-2 hidden text-sm leading-6 text-slate-300 sm:block sm:text-base sm:leading-7">{{ $slide->subtitle }}</p>@endif
                                @if($slide->button_label)<span class="mt-2 inline-flex min-h-9 items-center gap-2 text-xs font-semibold text-tech-300 sm:mt-5 sm:min-h-11 sm:border sm:border-tech-500 sm:bg-tech-700 sm:px-4 sm:py-3 sm:text-sm sm:text-white">{{ $slide->button_label }}<i data-lucide="arrow-right" class="h-4 w-4"></i></span>@endif
                            </div>
                        </div>
                    @else
                        <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->alt_text }}" class="h-full w-full object-contain object-center" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                        <span class="sr-only">{{ $slide->title }}</span>
                    @endif
                </a>
            </article>
        @endforeach

        @if($slideCount > 1)
            <div class="absolute inset-x-0 bottom-0 z-20 flex h-14 items-center justify-center border-t border-cyber-border bg-[#090d14]/95 px-3 backdrop-blur sm:px-5">
                <div class="flex min-w-0 items-center justify-center gap-1.5" aria-label="Choose a slide">
                    @foreach($slides as $slide)
                        <button type="button" data-slideshow-dot="{{ $loop->index }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ $loop->first ? 'Current slide' : 'Go to slide' }} {{ $loop->iteration }}" class="h-3 w-3 rounded-full border border-slate-500 bg-slate-800 outline-none transition aria-[current=true]:border-tech-300 aria-[current=true]:bg-tech-400 focus-visible:ring-2 focus-visible:ring-tech-300"></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
