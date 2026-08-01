@extends('layouts.storefront')
@section('title', 'FAQ')
@section('meta_description', 'Frequently asked questions about delivery, payment, warranty, and returns at MAAT TECHNOLOGIE BD.')
@section('content')
<main class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <p class="font-mono text-xs tracking-[.22em] text-tech-400">KNOWLEDGE_BASE</p>
    <h1 class="mt-2 text-3xl font-bold text-white">FAQ</h1>
    <div class="mt-8 space-y-4">
        @foreach([
            ['Q' => 'Delivery time?', 'A' => 'Dhaka: 1 to 3 working days. Outside Dhaka: 2 to 5 working days via Pathao or courier partner.'],
            ['Q' => 'Payment methods?', 'A' => 'Cash on Delivery is currently enabled. bKash and Nagad can be added next.'],
            ['Q' => 'Warranty?', 'A' => '12 months manufacturing warranty on eligible products unless otherwise stated.'],
            ['Q' => 'Return policy?', 'A' => 'Unused items in original packaging can be reviewed for return requests within 7 days.'],
        ] as $item)
        <div class="glass-panel p-5">
            <p class="font-semibold text-white">{{ $item['Q'] }}</p>
            <p class="mt-2 text-sm text-slate-400">{{ $item['A'] }}</p>
        </div>
        @endforeach
    </div>
</main>
@endsection
