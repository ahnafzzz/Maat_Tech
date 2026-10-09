@props(['product', 'showcase'])

@php
    $productUrl = route('products.show', $product->slug);
    $slides = collect($showcase['marketing_slides'] ?? []);
    $landscapeSlides = $slides->where('type', 'landscape')->values();
    $portraitSlides = $slides->where('type', 'portrait')->values();
    $slideCount = $slides->count();
@endphp

<section
    class="storefront-slideshow relative w-full overflow-hidden border-b border-cyber-border bg-[#080b10]"
    data-storefront-slideshow
    data-slideshow-interval="3000"
    data-slideshow-index="0"
    data-slideshow-animation="paused"
    aria-roledescription="carousel"
    aria-label="LED Swing-Arm Desk Lamp highlights"
>
    <div class="relative aspect-video">
        @foreach ($landscapeSlides as $slide)
            @php($responsiveImage = preg_replace('/\.webp$/', '-960.webp', $slide['image']))
            <article
                class="absolute inset-x-0 bottom-14 top-0 grid place-items-center transition-opacity duration-500 motion-reduce:transition-none"
                data-slideshow-slide
                data-active="{{ $loop->first ? 'true' : 'false' }}"
                aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                aria-label="Slide {{ $loop->iteration }} of {{ $slideCount }}"
            >
                <a href="{{ $productUrl }}" class="grid h-full w-full place-items-center bg-[#0b1016] outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300" @if(!$loop->first) tabindex="-1" @endif>
                    <img
                        src="{{ asset($slide['image']) }}"
                        srcset="{{ asset($responsiveImage) }} 960w, {{ asset($slide['image']) }} 1920w"
                        sizes="100vw"
                        alt="{{ $slide['alt'] }}"
                        width="1920"
                        height="960"
                        class="h-full w-full object-contain object-center"
                        @if($loop->first) fetchpriority="high" @else loading="lazy" decoding="async" @endif
                    >
                    <span class="sr-only">View {{ $product->name }}</span>
                </a>
            </article>
        @endforeach

        @foreach ($portraitSlides as $slide)
            @php($slideNumber = count($landscapeSlides) + $loop->iteration)
            @php($responsiveImage = preg_replace('/\.webp$/', '-560.webp', $slide['image']))
            <article
                class="absolute inset-x-0 bottom-14 top-0 grid transition-opacity duration-500 motion-reduce:transition-none"
                data-slideshow-slide
                data-active="false"
                aria-hidden="true"
                aria-label="Slide {{ $slideNumber }} of {{ $slideCount }}"
            >
                <a href="{{ $productUrl }}" tabindex="-1" class="grid h-full min-h-0 grid-cols-[.44fr_.56fr] overflow-hidden bg-[#0c1118] outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300 md:grid-cols-[minmax(18rem,.85fr)_minmax(20rem,1.15fr)]">
                    <div class="grid min-h-0 place-items-center overflow-hidden bg-black/20">
                        <img
                            src="{{ asset($slide['image']) }}"
                            srcset="{{ asset($responsiveImage) }} 560w, {{ asset($slide['image']) }} 1122w"
                            sizes="(min-width: 768px) 42vw, 44vw"
                            alt="{{ $slide['alt'] }}"
                            width="1122"
                            height="1402"
                            loading="lazy"
                            decoding="async"
                            class="h-full max-h-full w-full object-contain object-center"
                        >
                    </div>
                    <div class="flex items-center border-l border-cyber-border bg-[radial-gradient(circle_at_20%_20%,rgba(20,184,166,.12),transparent_58%)] p-2 sm:p-5 md:p-10 lg:p-14">
                        <div class="max-w-xl">
                            <p class="font-mono text-[10px] uppercase tracking-[.2em] text-tech-400">Flexible task lighting</p>
                            <h2 class="mt-1 text-sm font-bold leading-tight text-white sm:mt-3 sm:text-2xl lg:text-4xl">{{ $slide['heading'] }}</h2>
                            <p class="mt-2 hidden text-sm leading-6 text-slate-300 sm:block sm:text-base sm:leading-7">{{ $slide['copy'] }}</p>
                            <span class="mt-2 inline-flex min-h-9 items-center gap-2 text-xs font-semibold text-tech-300 sm:mt-5 sm:min-h-11 sm:border sm:border-tech-500 sm:bg-tech-700 sm:px-4 sm:py-3 sm:text-sm sm:text-white">View Product<i data-lucide="arrow-right" class="h-4 w-4"></i></span>
                        </div>
                    </div>
                </a>
            </article>
        @endforeach

        <div class="absolute inset-x-0 bottom-0 z-20 flex h-14 items-center justify-center border-t border-cyber-border bg-[#090d14]/95 px-3 backdrop-blur sm:px-5">
            <div class="flex min-w-0 items-center justify-center gap-1.5" aria-label="Choose a slide">
                @foreach (range(0, $slideCount - 1) as $index)
                    <button type="button" data-slideshow-dot="{{ $index }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" aria-label="{{ $index === 0 ? 'Current slide' : 'Go to slide' }} {{ $index + 1 }}" class="h-3 w-3 rounded-full border border-slate-500 bg-slate-800 outline-none transition aria-[current=true]:border-tech-300 aria-[current=true]:bg-tech-400 focus-visible:ring-2 focus-visible:ring-tech-300"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
