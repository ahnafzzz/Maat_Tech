<?php

use App\Http\Controllers\Api\CartController as ApiCartController;
use App\Http\Controllers\Api\OrderController as ApiOrderController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StorefrontController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/products/{slug}', [HomeController::class, 'show'])->name('products.show');
Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');
Route::view('/faq', 'faq')->name('faq');
Route::view('/shipping', 'shipping')->name('shipping');
Route::view('/returns', 'returns')->name('returns');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::view('/refund', 'refund')->name('refund');
Route::get('/sitemap.xml', function () {
    $urls = collect([
        route('home'),
        route('products'),
        route('about'),
        route('contact'),
        route('faq'),
        route('shipping'),
        route('returns'),
        route('privacy'),
        route('terms'),
        route('refund'),
    ])->merge(Product::where('status', 'active')->pluck('slug')->map(fn (string $slug) => route('products.show', $slug)));

    $xml = view('sitemap', ['urls' => $urls])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart.index');
Route::post('/cart/add/{product}', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::post('/cart/update/{product}', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::post('/cart/remove/{product}', [StorefrontController::class, 'removeFromCart'])->name('cart.remove');
Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [StorefrontController::class, 'placeOrder'])->middleware('throttle:checkout')->name('checkout.place');

Route::get('/orders', [StorefrontController::class, 'orders'])->name('orders.index');
Route::get('/wishlist', [StorefrontController::class, 'wishlist'])->name('wishlist.index');
Route::post('/wishlist/{product}', [StorefrontController::class, 'toggleWishlist'])->name('wishlist.toggle');

Route::middleware('guest')->group(function () {
    Route::get('/register', [CustomerAuthController::class, 'create'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'store'])->middleware('throttle:auth')->name('register.store');
    Route::get('/login', [CustomerAuthController::class, 'login'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:auth')->name('login.store');
    Route::get('/forgot-password', [CustomerPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [CustomerPasswordController::class, 'store'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', [CustomerPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [CustomerPasswordController::class, 'update'])->middleware('throttle:auth')->name('password.update');
});

Route::post('/logout', [CustomerAuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/dashboard', [StorefrontController::class, 'dashboard'])->middleware('auth')->name('dashboard');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminController::class, 'login'])->name('login');
        Route::post('/login', [AdminController::class, 'authenticate'])->middleware('throttle:admin-login')->name('login.store');
        Route::get('/two-factor', [AdminController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
        Route::post('/two-factor', [AdminController::class, 'verifyTwoFactorChallenge'])->middleware('throttle:admin-login')->name('two-factor.verify');
    });

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::post('/two-factor/toggle', [AdminController::class, 'toggleTwoFactor'])->name('two-factor.toggle');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
        Route::patch('/products/{product}', [AdminController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{product}', [AdminController::class, 'destroyProduct'])->name('products.destroy');
        Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::patch('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');
        Route::patch('/orders/{order}', [AdminController::class, 'updateOrder'])->name('orders.update');
        Route::post('/invitations', [AdminController::class, 'requestInvitation'])->name('invitations.store');
        Route::post('/invitations/{requestItem}/approve', [AdminController::class, 'approveInvitation'])->name('invitations.approve');
        Route::post('/invitations/{requestItem}/reject', [AdminController::class, 'rejectInvitation'])->name('invitations.reject');
    });
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);
    Route::post('/products', [ApiProductController::class, 'store']);
    Route::get('/products/{id}', [ApiProductController::class, 'show']);
    Route::put('/products/{id}', [ApiProductController::class, 'update']);
    Route::delete('/products/{id}', [ApiProductController::class, 'destroy']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/cart', [ApiCartController::class, 'index']);
        Route::post('/cart', [ApiCartController::class, 'store']);
        Route::delete('/cart/{id}', [ApiCartController::class, 'destroy']);

        Route::get('/orders', [ApiOrderController::class, 'index']);
        Route::post('/orders', [ApiOrderController::class, 'store']);
        Route::get('/orders/{order}', [ApiOrderController::class, 'show'])->middleware('ensure.order.owner');
    });
});
