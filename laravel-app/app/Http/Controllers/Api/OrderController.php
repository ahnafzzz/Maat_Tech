<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService) {}

    public function index(Request $request)
    {
        return Order::where('user_id', $request->user('web')->id)->latest()->get();
    }

    public function store(Request $request)
    {
        $attemptKey = Validator::make(['idempotency_key' => $request->header('Idempotency-Key')], [
            'idempotency_key' => ['required', 'string', 'max:128', 'regex:'.CheckoutService::IDEMPOTENCY_KEY_PATTERN],
        ])->validate()['idempotency_key'];

        $data = $request->validate([
            'payment_method' => ['required', 'in:cod'],
            'shipping_method' => ['required', 'in:pathao'],
            'shipping_address' => 'required|array',
            'shipping_address.name' => ['sometimes', 'required', 'string', 'max:120'],
            'shipping_address.phone' => ['sometimes', 'required', 'string', 'max:30'],
            'shipping_address.address' => ['required', 'string', 'max:2000'],
            'shipping_address.district' => ['nullable', 'string', 'max:100'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $districtInput = $data['shipping_address']['district'] ?? null;
        $cityInput = $data['shipping_address']['city'] ?? null;
        if ($districtInput === null && $cityInput === null) {
            throw ValidationException::withMessages(['shipping_address.district' => 'A district or city is required.']);
        }
        $district = $districtInput === null ? null : $this->checkoutService->normalizeDistrict($districtInput, 'shipping_address.district');
        $city = $cityInput === null ? null : $this->checkoutService->normalizeDistrict($cityInput, 'shipping_address.city');
        if ($district && $city && $district !== $city) {
            throw ValidationException::withMessages(['shipping_address.city' => 'City and district must identify the same district.']);
        }

        $customer = $request->user('web');
        $contact = Validator::make(['shipping_address' => [
            'name' => trim((string) ($data['shipping_address']['name'] ?? $customer->name)),
            'phone' => trim((string) ($data['shipping_address']['phone'] ?? $customer->phone)),
            'address' => trim($data['shipping_address']['address']),
        ]], [
            'shipping_address.name' => ['required', 'string', 'max:120'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.address' => ['required', 'string', 'max:2000'],
        ])->validate()['shipping_address'];

        $result = $this->checkoutService->checkout($customer, [], [
            'name' => $contact['name'],
            'phone' => $contact['phone'],
            'address' => $contact['address'],
            'district' => $district ?? $city,
            'customer_note' => $data['customer_note'] ?? null,
        ], $attemptKey);

        return response()->json($result->order->load('items.product'), 201)
            ->header('Idempotent-Replayed', $result->replayed ? 'true' : 'false');
    }
}
