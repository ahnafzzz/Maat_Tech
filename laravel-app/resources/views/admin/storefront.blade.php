@extends('layouts.admin')
@section('title', 'Storefront CMS')

@php
    $input = 'mt-2 w-full rounded-lg border border-cyber-border bg-[#080c12] p-3 text-sm text-white outline-none focus:border-tech-500';
    $label = 'block font-mono text-[10px] text-slate-400';
    $button = 'rounded-lg border border-tech-400 bg-tech-600 px-5 py-3 text-xs font-mono text-white hover:bg-tech-500';
@endphp

@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="font-mono text-xs tracking-[.2em] text-tech-400">STRUCTURED_CONTENT_CONTROL</p><h1 class="mt-2 text-3xl font-bold text-white">Storefront CMS</h1><p class="mt-2 max-w-3xl text-sm text-slate-400">Edit storefront content and presentation through validated fields. No raw HTML is accepted.</p></div>
        <a href="{{ route('home') }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 border border-tech-600 px-4 py-3 text-xs font-mono text-tech-300">PREVIEW_STOREFRONT<i data-lucide="external-link" class="h-4 w-4"></i></a>
    </header>

    <nav class="panel mb-7 flex flex-wrap gap-2 rounded-lg p-3 text-xs font-mono">
        <a href="#identity" class="border border-cyber-border px-3 py-2 text-tech-300">IDENTITY</a>
        <a href="#homepage" class="border border-cyber-border px-3 py-2 text-tech-300">HOMEPAGE</a>
        <a href="#pages" class="border border-cyber-border px-3 py-2 text-tech-300">PAGES</a>
        <a href="#navigation" class="border border-cyber-border px-3 py-2 text-tech-300">NAVIGATION</a>
        <a href="#slides" class="border border-cyber-border px-3 py-2 text-tech-300">SLIDES</a>
    </nav>

    <form method="POST" action="{{ route('admin.storefront.settings.update') }}" enctype="multipart/form-data" class="space-y-7">
        @csrf @method('PATCH')
        <section id="identity" class="panel scroll-mt-24 rounded-lg p-5">
            <h2 class="font-mono text-sm text-white">IDENTITY_CONTACT_THEME</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <label class="{{ $label }}">SITE_NAME<input class="{{ $input }}" name="site_name" required value="{{ old('site_name', $settings->site_name) }}"></label>
                <label class="{{ $label }}">TAGLINE<input class="{{ $input }}" name="tagline" value="{{ old('tagline', $settings->tagline) }}"></label>
                <label class="{{ $label }}">LOGO_ALT_TEXT<input class="{{ $input }}" name="logo_alt" required value="{{ old('logo_alt', $settings->logo_alt) }}"></label>
                <label class="{{ $label }} md:col-span-2 lg:col-span-3">DEFAULT_SEO_DESCRIPTION<textarea class="{{ $input }}" name="default_meta_description" rows="2">{{ old('default_meta_description', $settings->default_meta_description) }}</textarea></label>
                <label class="{{ $label }}">SUPPORT_EMAIL<input type="email" class="{{ $input }}" name="support_email" value="{{ old('support_email', $settings->support_email) }}"></label>
                <label class="{{ $label }}">PHONE_DISPLAY<input class="{{ $input }}" name="phone_display" value="{{ old('phone_display', $settings->phone_display) }}"></label>
                <label class="{{ $label }}">WHATSAPP_DIGITS<input class="{{ $input }}" name="whatsapp_number" inputmode="numeric" value="{{ old('whatsapp_number', $settings->whatsapp_number) }}"></label>
                <label class="{{ $label }} md:col-span-2">ADDRESS<textarea class="{{ $input }}" name="address" rows="2">{{ old('address', $settings->address) }}</textarea></label>
                <label class="{{ $label }}">BUSINESS_HOURS<input class="{{ $input }}" name="business_hours" value="{{ old('business_hours', $settings->business_hours) }}"></label>
                <label class="{{ $label }}">FACEBOOK_URL<input type="url" class="{{ $input }}" name="facebook_url" value="{{ old('facebook_url', $settings->facebook_url) }}"></label>
                <label class="{{ $label }}">INSTAGRAM_URL<input type="url" class="{{ $input }}" name="instagram_url" value="{{ old('instagram_url', $settings->instagram_url) }}"></label>
                <div class="rounded-lg border border-cyber-border p-4">
                    <img src="{{ $settings->logoUrl() }}" alt="" class="mb-3 h-16 w-24 object-contain">
                    <label class="{{ $label }}">REPLACE_LOGO<input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-xs text-slate-400"></label>
                    <label class="mt-3 flex items-center gap-2 text-xs text-slate-400"><input type="checkbox" name="remove_logo" value="1"> Restore default logo</label>
                </div>
                <label class="{{ $label }}">ACCENT_COLOR<input type="color" class="{{ $input }} h-12" name="accent_color" value="{{ old('accent_color', $settings->accent_color) }}"></label>
                <label class="{{ $label }}">PRIMARY_COLOR<input type="color" class="{{ $input }} h-12" name="primary_color" value="{{ old('primary_color', $settings->primary_color) }}"></label>
                <label class="{{ $label }}">BACKGROUND_COLOR<input type="color" class="{{ $input }} h-12" name="background_color" value="{{ old('background_color', $settings->background_color) }}"></label>
                <label class="{{ $label }}">PANEL_COLOR<input type="color" class="{{ $input }} h-12" name="panel_color" value="{{ old('panel_color', $settings->panel_color) }}"></label>
            </div>
        </section>

        <section id="homepage" class="panel scroll-mt-24 rounded-lg p-5">
            <h2 class="font-mono text-sm text-white">HOMEPAGE_HEADER_FOOTER</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <label class="{{ $label }}">HOME_META_TITLE<input class="{{ $input }}" name="home_meta_title" required value="{{ old('home_meta_title', $settings->home_meta_title) }}"></label>
                <label class="{{ $label }} md:col-span-2">HOME_META_DESCRIPTION<input class="{{ $input }}" name="home_meta_description" value="{{ old('home_meta_description', $settings->home_meta_description) }}"></label>
                <label class="{{ $label }}">HERO_BADGE<input class="{{ $input }}" name="hero_badge" value="{{ old('hero_badge', $settings->hero_badge) }}"></label>
                <label class="{{ $label }} md:col-span-2">HERO_HEADING_OVERRIDE<input class="{{ $input }}" name="hero_heading" value="{{ old('hero_heading', $settings->hero_heading) }}" placeholder="Leave blank to use product name"></label>
                <label class="{{ $label }} md:col-span-2 lg:col-span-3">HERO_COPY_OVERRIDE<textarea class="{{ $input }}" name="hero_copy" rows="3" placeholder="Leave blank to use product description">{{ old('hero_copy', $settings->hero_copy) }}</textarea></label>
                <label class="{{ $label }}">FEATURED_PRODUCT<select class="{{ $input }}" name="featured_product_id"><option value="">Automatic featured product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('featured_product_id', $settings->featured_product_id) == $product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
                <label class="{{ $label }}">PRIMARY_BUTTON_LABEL<input class="{{ $input }}" name="hero_primary_label" required value="{{ old('hero_primary_label', $settings->hero_primary_label) }}"></label>
                <label class="{{ $label }}">SECONDARY_BUTTON_LABEL<input class="{{ $input }}" name="hero_secondary_label" required value="{{ old('hero_secondary_label', $settings->hero_secondary_label) }}"></label>
                <label class="{{ $label }} md:col-span-2">HERO_NOTE<input class="{{ $input }}" name="hero_note" value="{{ old('hero_note', $settings->hero_note) }}"></label>
                <label class="{{ $label }}">SHOP_LABEL<input class="{{ $input }}" name="shop_label" required value="{{ old('shop_label', $settings->shop_label) }}"></label>
                <label class="{{ $label }}">ACCOUNT_LABEL<input class="{{ $input }}" name="account_label" required value="{{ old('account_label', $settings->account_label) }}"></label>
                <label class="{{ $label }}">SIGN_IN_LABEL<input class="{{ $input }}" name="sign_in_label" required value="{{ old('sign_in_label', $settings->sign_in_label) }}"></label>
                <label class="{{ $label }}">FOOTER_COPYRIGHT<input class="{{ $input }}" name="footer_copyright" required value="{{ old('footer_copyright', $settings->footer_copyright) }}"></label>
                <label class="{{ $label }}">FOOTER_STATUS<input class="{{ $input }}" name="footer_status" required value="{{ old('footer_status', $settings->footer_status) }}"></label>
                <label class="{{ $label }}">FOOTER_SUPPORT_LABEL<input class="{{ $input }}" name="footer_support_label" required value="{{ old('footer_support_label', $settings->footer_support_label) }}"></label>
                <label class="{{ $label }}">WHATSAPP_CTA_LABEL<input class="{{ $input }}" name="whatsapp_cta_label" required value="{{ old('whatsapp_cta_label', $settings->whatsapp_cta_label) }}"></label>
                @foreach(['slideshow_enabled' => 'Show homepage slideshow', 'wishlist_enabled' => 'Show wishlist control', 'cart_enabled' => 'Show cart control'] as $field => $caption)
                    <label class="flex items-center gap-3 rounded-lg border border-cyber-border bg-[#080c12] p-3 text-sm text-slate-300"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings->$field))>{{ $caption }}</label>
                @endforeach
            </div>
            <button class="{{ $button }} mt-5">SAVE_GLOBAL_STOREFRONT</button>
        </section>
    </form>

    <section id="pages" class="mt-7 scroll-mt-24 space-y-5">
        <div><h2 class="font-mono text-sm text-white">CONTENT_PAGES</h2><p class="mt-2 text-sm text-slate-500">Paragraphs are separated by a blank line. Cards use paired heading and text fields.</p></div>
        @foreach($pages as $page)
            <form method="POST" action="{{ route('admin.storefront.pages.update', $page) }}" class="panel rounded-lg p-5">
                @csrf @method('PATCH')
                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold text-white">/{{ $page->slug }}</h3><label class="flex items-center gap-2 text-xs text-slate-300"><input type="hidden" name="is_visible" value="0"><input type="checkbox" name="is_visible" value="1" @checked($page->is_visible)> Visible</label></div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="{{ $label }}">EYEBROW<input class="{{ $input }}" name="eyebrow" value="{{ $page->eyebrow }}"></label>
                    <label class="{{ $label }}">PAGE_TITLE<input class="{{ $input }}" name="title" required value="{{ $page->title }}"></label>
                    <label class="{{ $label }}">SEO_TITLE<input class="{{ $input }}" name="meta_title" value="{{ $page->meta_title }}"></label>
                    <label class="{{ $label }}">SEO_DESCRIPTION<textarea class="{{ $input }}" name="meta_description" rows="2">{{ $page->meta_description }}</textarea></label>
                    <label class="{{ $label }} md:col-span-2">PAGE_PARAGRAPHS<textarea class="{{ $input }}" name="body_text" rows="7">{{ implode("\n\n", $page->body ?? []) }}</textarea></label>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach(collect($page->items ?? [])->concat([['title' => '', 'text' => ''], ['title' => '', 'text' => '']]) as $item)
                        <div class="rounded-lg border border-cyber-border p-3"><label class="{{ $label }}">CARD_HEADING<input class="{{ $input }}" name="items[{{ $loop->index }}][title]" value="{{ $item['title'] }}"></label><label class="{{ $label }} mt-3">CARD_TEXT<textarea class="{{ $input }}" name="items[{{ $loop->index }}][text]" rows="3">{{ $item['text'] }}</textarea></label></div>
                    @endforeach
                </div>
                <button class="{{ $button }} mt-4">SAVE_{{ strtoupper($page->slug) }}</button>
            </form>
        @endforeach
    </section>

    <section id="navigation" class="mt-7 scroll-mt-24">
        <h2 class="font-mono text-sm text-white">NAVIGATION_LINKS</h2>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @foreach($links as $linkItem)
                <article class="panel rounded-lg p-4">
                    <form method="POST" action="{{ route('admin.storefront.navigation.update', $linkItem) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <label class="{{ $label }}">LABEL<input class="{{ $input }}" name="label" required value="{{ $linkItem->label }}"></label>
                        <label class="{{ $label }}">URL<input class="{{ $input }}" name="url" required value="{{ $linkItem->url }}"></label>
                        <label class="{{ $label }}">LOCATION<select class="{{ $input }}" name="location"><option value="header" @selected($linkItem->location === 'header')>Header</option><option value="footer" @selected($linkItem->location === 'footer')>Footer</option></select></label>
                        <div class="grid grid-cols-2 gap-2"><label class="{{ $label }}">COLUMN<input type="number" min="1" max="4" class="{{ $input }}" name="column" value="{{ $linkItem->column }}"></label><label class="{{ $label }}">ORDER<input type="number" min="0" class="{{ $input }}" name="sort_order" value="{{ $linkItem->sort_order }}"></label></div>
                        <label class="flex items-center gap-2 text-xs text-slate-300"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($linkItem->is_active)> Active</label>
                        <label class="flex items-center gap-2 text-xs text-slate-300"><input type="hidden" name="open_new_tab" value="0"><input type="checkbox" name="open_new_tab" value="1" @checked($linkItem->open_new_tab)> Open new tab</label>
                        <button class="{{ $button }} sm:col-span-2">UPDATE_LINK</button>
                    </form>
                    <form method="POST" action="{{ route('admin.storefront.navigation.destroy', $linkItem) }}" class="mt-2" onsubmit="return confirm('Delete this navigation link?')">@csrf @method('DELETE')<button class="text-xs font-mono text-rose-300">DELETE_LINK</button></form>
                </article>
            @endforeach
            <form method="POST" action="{{ route('admin.storefront.navigation.store') }}" class="panel grid gap-3 rounded-lg border-dashed p-4 sm:grid-cols-2">
                @csrf
                <h3 class="font-mono text-xs text-tech-300 sm:col-span-2">ADD_LINK</h3>
                <label class="{{ $label }}">LABEL<input class="{{ $input }}" name="label" required></label><label class="{{ $label }}">URL<input class="{{ $input }}" name="url" required placeholder="/about or https://..."></label>
                <label class="{{ $label }}">LOCATION<select class="{{ $input }}" name="location"><option value="header">Header</option><option value="footer">Footer</option></select></label>
                <div class="grid grid-cols-2 gap-2"><label class="{{ $label }}">COLUMN<input type="number" min="1" max="4" class="{{ $input }}" name="column" value="1"></label><label class="{{ $label }}">ORDER<input type="number" min="0" class="{{ $input }}" name="sort_order" value="100"></label></div>
                <input type="hidden" name="is_active" value="1"><input type="hidden" name="open_new_tab" value="0"><button class="{{ $button }} sm:col-span-2">CREATE_LINK</button>
            </form>
        </div>
    </section>

    <section id="slides" class="mt-7 scroll-mt-24">
        <h2 class="font-mono text-sm text-white">HOMEPAGE_SLIDES</h2>
        <div class="mt-4 grid gap-5 lg:grid-cols-2">
            @foreach($banners as $banner)
                <article class="panel rounded-lg p-4">
                    <img src="{{ $banner->imageUrl() }}" alt="" class="mb-4 aspect-video w-full bg-black/30 object-contain">
                    <form method="POST" action="{{ route('admin.storefront.slides.update', $banner) }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <label class="{{ $label }}">TITLE<input class="{{ $input }}" name="title" required value="{{ $banner->title }}"></label><label class="{{ $label }}">EYEBROW<input class="{{ $input }}" name="eyebrow" value="{{ $banner->eyebrow }}"></label>
                        <label class="{{ $label }} sm:col-span-2">DESCRIPTION<textarea class="{{ $input }}" name="subtitle" rows="2">{{ $banner->subtitle }}</textarea></label>
                        <label class="{{ $label }} sm:col-span-2">ALT_TEXT<input class="{{ $input }}" name="alt_text" required value="{{ $banner->alt_text }}"></label>
                        <label class="{{ $label }}">LINK_URL<input class="{{ $input }}" name="link_url" value="{{ $banner->link_url }}"></label><label class="{{ $label }}">BUTTON_LABEL<input class="{{ $input }}" name="button_label" value="{{ $banner->button_label }}"></label>
                        <label class="{{ $label }}">LAYOUT<select class="{{ $input }}" name="layout"><option value="image" @selected($banner->layout === 'image')>Image only</option><option value="split" @selected($banner->layout === 'split')>Image + copy</option></select></label><label class="{{ $label }}">ORDER<input type="number" min="0" class="{{ $input }}" name="sort_order" value="{{ $banner->sort_order }}"></label>
                        <label class="{{ $label }} sm:col-span-2">REPLACE_IMAGE<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-xs text-slate-400"></label>
                        <label class="flex items-center gap-2 text-xs text-slate-300"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($banner->is_active)> Active</label>
                        <button class="{{ $button }}">UPDATE_SLIDE</button>
                    </form>
                    <form method="POST" action="{{ route('admin.storefront.slides.destroy', $banner) }}" class="mt-3" onsubmit="return confirm('Delete this homepage slide?')">@csrf @method('DELETE')<button class="text-xs font-mono text-rose-300">DELETE_SLIDE</button></form>
                </article>
            @endforeach
            <form method="POST" action="{{ route('admin.storefront.slides.store') }}" enctype="multipart/form-data" class="panel grid h-fit gap-3 rounded-lg border-dashed p-4 sm:grid-cols-2">
                @csrf
                <h3 class="font-mono text-xs text-tech-300 sm:col-span-2">ADD_SLIDE</h3>
                <label class="{{ $label }}">TITLE<input class="{{ $input }}" name="title" required></label><label class="{{ $label }}">EYEBROW<input class="{{ $input }}" name="eyebrow"></label>
                <label class="{{ $label }} sm:col-span-2">DESCRIPTION<textarea class="{{ $input }}" name="subtitle" rows="2"></textarea></label>
                <label class="{{ $label }} sm:col-span-2">ALT_TEXT<input class="{{ $input }}" name="alt_text" required></label>
                <label class="{{ $label }}">LINK_URL<input class="{{ $input }}" name="link_url" value="/products"></label><label class="{{ $label }}">BUTTON_LABEL<input class="{{ $input }}" name="button_label" value="View products"></label>
                <label class="{{ $label }}">LAYOUT<select class="{{ $input }}" name="layout"><option value="image">Image only</option><option value="split">Image + copy</option></select></label><label class="{{ $label }}">ORDER<input type="number" min="0" class="{{ $input }}" name="sort_order" value="100"></label>
                <label class="{{ $label }} sm:col-span-2">IMAGE<input type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-xs text-slate-400"></label>
                <input type="hidden" name="is_active" value="1"><button class="{{ $button }} sm:col-span-2">CREATE_SLIDE</button>
            </form>
        </div>
    </section>
</main>
@endsection
