@extends('layouts.storefront')
@section('title', 'Checkout')
@section('meta_description', 'Complete your MAAT Technologies BD order with Cash on Delivery and delivery details.')
@section('content')
<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.22em] text-tech-400">CHECKOUT_PROTOCOL</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Delivery & Payment</h1>
        </div>
        <a href="{{ route('cart.index') }}" class="text-xs font-mono text-tech-400 hover:text-tech-300">RETURN_TO_CART</a>
    </header>

    @guest('web')
        <div class="mb-6 rounded-lg border border-tech-700/70 bg-tech-950/50 p-4 text-sm text-tech-200">You can place an order as guest now, then create or sign in to an account later to manage future orders and saved addresses.</div>
    @endguest

    <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <form method="POST" action="{{ route('checkout.place') }}" class="glass-panel p-6">
            @csrf
            <input type="hidden" name="checkout_attempt_key" value="{{ $checkoutAttemptKey }}">
            @if($buyNowToken)<input type="hidden" name="buy_now_token" value="{{ $buyNowToken }}">@endif
            <h2 class="font-mono text-sm text-tech-300">DELIVERY_IDENTITY</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="text-xs font-mono text-slate-400">FULL_NAME<input name="name" required value="{{ old('name', auth('web')->user()?->name) }}" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] p-3 text-sm text-white outline-none focus:border-tech-500"></label>
                <label class="text-xs font-mono text-slate-400">PHONE_NUMBER<input name="phone" required value="{{ old('phone', auth('web')->user()?->phone) }}" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] p-3 text-sm text-white outline-none focus:border-tech-500"></label>
                <label class="text-xs font-mono text-slate-400">DISTRICT
                    <select id="checkout-district" name="district" required class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] p-3 text-sm text-white outline-none focus:border-tech-500">
                        <option value="">Select district</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district }}" @selected(old('district', $selectedDistrict) === $district)>{{ $district }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-mono text-slate-400">PAYMENT_METHOD<input disabled value="Cash on Delivery (COD)" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#121722] p-3 text-sm text-slate-400"></label>
            </div>
            <label class="mt-4 block text-xs font-mono text-slate-400">FULL_ADDRESS<textarea name="address" required rows="4" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] p-3 text-sm text-white outline-none focus:border-tech-500">{{ old('address', auth('web')->user()?->address) }}</textarea></label>
            <label class="mt-4 block text-xs font-mono text-slate-400">CUSTOMER_NOTE<textarea name="customer_note" rows="3" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] p-3 text-sm text-white outline-none focus:border-tech-500">{{ old('customer_note') }}</textarea></label>
            <div class="mt-6 rounded-lg border border-cyber-border bg-[#0d121b] p-4 text-xs leading-6 text-slate-400">
                <p>Current payment method: Cash on Delivery only.</p>
                <p class="font-semibold text-emerald-300">Free delivery all across Bangladesh.</p>
                <p>Our team may contact you on WhatsApp or phone before dispatch.</p>
            </div>
            <button class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg border border-tech-400 bg-tech-600 px-4 py-3 text-sm font-mono text-white hover:bg-tech-500"><i data-lucide="shield-check" class="h-4 w-4"></i>PLACE_COD_ORDER</button>
        </form>

        <aside class="glass-panel h-fit p-6">
            <h2 class="font-mono text-sm text-tech-300">ORDER_SUMMARY</h2>
            <div class="mt-5 space-y-3 border-b border-cyber-border pb-5 text-sm text-slate-300">
                @foreach ($items as $item)
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 gap-3">
                            <img src="{{ $item['product']->primaryImageUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-cyber-border bg-slate-900 object-contain">
                            <div>
                            <p class="font-medium text-white">{{ $item['product']->name }}</p>
                            @if($item['variant_label'])<p class="mt-1 text-xs text-slate-400">Color: {{ $item['variant_label'] }}</p>@endif
                            <p class="mt-1 text-xs text-slate-500">৳{{ number_format($item['product']->final_price, 0) }} × {{ $item['quantity'] }}</p>
                            </div>
                        </div>
                        <span>BDT {{ number_format($item['line_total']) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 space-y-2 text-sm text-slate-400">
                <div class="flex justify-between"><span>Subtotal</span><span>BDT {{ number_format($subtotal) }}</span></div>
                <div class="flex justify-between"><span>Delivery</span><span id="shipping-fee-label" class="font-semibold text-emerald-300">FREE / ৳0</span></div>
                <div class="flex justify-between"><span>Payment</span><span>Cash on Delivery</span></div>
            </div>
            <div class="mt-5 border-t border-cyber-border pt-4">
                <div class="flex justify-between text-base font-semibold text-white"><span>Final payable</span><span id="checkout-total-label">৳{{ number_format((float) $subtotal, 0) }}</span></div>
            </div>
            @if($storefrontSettings->whatsappUrl())<a href="{{ $storefrontSettings->whatsappUrl('I need checkout help with my '.$storefrontSettings->site_name.' order.') }}" target="_blank" rel="noreferrer" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-700 bg-emerald-950/40 px-4 py-3 text-xs font-mono text-emerald-300 hover:bg-emerald-900/50"><i data-lucide="message-circle" class="h-4 w-4"></i>NEED_HELP_ON_WHATSAPP</a>@endif
        </aside>
    </div>
</main>
@endsection
