@extends('layouts.storefront')

@section('title', $page->meta_title ?: $page->title)
@section('meta_description', $page->meta_description ?: $storefrontSettings->default_meta_description)

@section('content')
<main class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    @if($page->eyebrow)<p class="font-mono text-xs tracking-[.22em] text-tech-400">{{ $page->eyebrow }}</p>@endif
    <h1 class="mt-2 text-3xl font-bold text-white">{{ $page->title }}</h1>

    @if($page->slug === 'contact')
        <div class="mt-8 glass-panel space-y-4 p-6 text-sm">
            @if($storefrontSettings->phone_display && $storefrontSettings->whatsappUrl())<p><span class="text-slate-500">Phone / WhatsApp:</span> <a href="{{ $storefrontSettings->whatsappUrl() }}" class="text-tech-300">{{ $storefrontSettings->phone_display }}</a></p>@endif
            @if($storefrontSettings->support_email)<p><span class="text-slate-500">Email:</span> <a href="mailto:{{ $storefrontSettings->support_email }}" class="text-tech-300">{{ $storefrontSettings->support_email }}</a></p>@endif
            @if($storefrontSettings->address)<p><span class="text-slate-500">Address:</span> <span class="text-slate-300">{{ $storefrontSettings->address }}</span></p>@endif
            @if($storefrontSettings->instagram_url)<p><span class="text-slate-500">Instagram:</span> <a href="{{ $storefrontSettings->instagram_url }}" target="_blank" rel="noreferrer" class="text-tech-300">Instagram</a></p>@endif
            @if($storefrontSettings->facebook_url)<p><span class="text-slate-500">Facebook:</span> <a href="{{ $storefrontSettings->facebook_url }}" target="_blank" rel="noreferrer" class="text-tech-300">Facebook</a></p>@endif
            @if($storefrontSettings->business_hours)<p class="text-slate-500">{{ $storefrontSettings->business_hours }}</p>@endif
        </div>
    @endif

    @if(!empty($page->body))
        <div class="mt-8 glass-panel space-y-4 p-6 text-sm leading-7 text-slate-400">
            @foreach($page->body as $paragraph)<p>{{ $paragraph }}</p>@endforeach
        </div>
    @endif

    @if(!empty($page->items))
        <div class="mt-8 space-y-4">
            @foreach($page->items as $item)
                <article class="glass-panel p-5">
                    <h2 class="font-semibold text-white">{{ $item['title'] }}</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-400">{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>
    @endif
</main>
@endsection
