<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminStorefrontController;
use App\Http\Controllers\Api\CartController as ApiCartController;
use App\Http\Controllers\Api\OrderController as ApiOrderController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StorefrontPageController;
use App\Models\Product;
use App\Models\StorefrontPage;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/products/{slug}', [HomeController::class, 'show'])->name('products.show');
foreach (['about', 'contact', 'faq', 'shipping', 'returns', 'privacy', 'terms', 'refund'] as $contentPage) {
    Route::get('/'.$contentPage, [StorefrontPageController::class, 'show'])
        ->defaults('contentPage', $contentPage)->name($contentPage);
}
Route::get('/sitemap.xml', function () {
    $urls = collect([
        route('home'),
        route('products'),
    ])->merge(
        StorefrontPage::where('is_visible', true)->pluck('slug')->map(fn (string $slug) => route($slug))
    )->merge(Product::published()->pluck('slug')->map(fn (string $slug) => route('products.show', $slug)));

    $xml = view('sitemap', ['urls' => $urls])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart.index');
Route::post('/cart/add/{product}', [StorefrontController::class, 'addToCart'])->whereNumber('product')->middleware('throttle:cart-mutation')->name('cart.add');
Route::post('/buy-now/{product}', [StorefrontController::class, 'buyNow'])->whereNumber('product')->middleware('throttle:checkout-start')->name('buy-now');
Route::post('/cart/update/{product}', [StorefrontController::class, 'updateCart'])->whereNumber('product')->middleware('throttle:cart-mutation')->name('cart.update');
Route::post('/cart/remove/{product}', [StorefrontController::class, 'removeFromCart'])->whereNumber('product')->middleware('throttle:cart-mutation')->name('cart.remove');
Route::post('/cart/clear', [StorefrontController::class, 'clearCart'])->middleware('throttle:cart-mutation')->name('cart.clear');
Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [StorefrontController::class, 'placeOrder'])->middleware('throttle:checkout')->name('checkout.place');

Route::get('/orders', [StorefrontController::class, 'orders'])->name('orders.index');
Route::get('/wishlist', [StorefrontController::class, 'wishlist'])->name('wishlist.index');
Route::post('/wishlist/{product}', [StorefrontController::class, 'toggleWishlist'])->whereNumber('product')->name('wishlist.toggle');

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
    Route::get('/invitations/accept/{selector}', [AdminController::class, 'showInvitationAcceptance'])
        ->where('selector', '[a-f0-9]{32}')->name('invitations.accept.show');
    Route::post('/invitations/accept/{selector}', [AdminController::class, 'acceptInvitation'])
        ->where('selector', '[a-f0-9]{32}')->middleware('throttle:admin-invitation-accept')->name('invitations.accept');

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminController::class, 'login'])->name('login');
        Route::post('/login', [AdminController::class, 'authenticate'])->middleware('throttle:admin-login')->name('login.store');
        Route::get('/two-factor', [AdminController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
        Route::post('/two-factor', [AdminController::class, 'verifyTwoFactorChallenge'])->middleware('throttle:admin-two-factor')->name('two-factor.verify');
    });

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/storefront', [AdminStorefrontController::class, 'index'])->name('storefront');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::get('/sales', [AdminController::class, 'sales'])->name('sales');
        Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::post('/two-factor/toggle', [AdminController::class, 'toggleTwoFactor'])->name('two-factor.toggle');
        Route::get('/two-factor/enrollment', [AdminController::class, 'showTwoFactorEnrollment'])->name('two-factor.enrollment');
        Route::post('/two-factor/enrollment', [AdminController::class, 'verifyTwoFactorEnrollment'])->middleware('throttle:admin-two-factor')->name('two-factor.enrollment.verify');
        Route::middleware('admin.2fa')->group(function () {
        Route::patch('/storefront/settings', [AdminStorefrontController::class, 'updateSettings'])->name('storefront.settings.update');
        Route::patch('/storefront/pages/{page}', [AdminStorefrontController::class, 'updatePage'])->name('storefront.pages.update');
        Route::post('/storefront/navigation', [AdminStorefrontController::class, 'storeLink'])->name('storefront.navigation.store');
        Route::patch('/storefront/navigation/{link}', [AdminStorefrontController::class, 'updateLink'])->name('storefront.navigation.update');
        Route::delete('/storefront/navigation/{link}', [AdminStorefrontController::class, 'destroyLink'])->name('storefront.navigation.destroy');
        Route::post('/storefront/slides', [AdminStorefrontController::class, 'storeBanner'])->name('storefront.slides.store');
        Route::patch('/storefront/slides/{banner}', [AdminStorefrontController::class, 'updateBanner'])->name('storefront.slides.update');
        Route::delete('/storefront/slides/{banner}', [AdminStorefrontController::class, 'destroyBanner'])->name('storefront.slides.destroy');
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
        Route::post('/invitations/{requestItem}/resend', [AdminController::class, 'resendInvitation'])
            ->middleware('throttle:admin-invitation-resend')->name('invitations.resend');
        Route::post('/invitations/{requestItem}/revoke', [AdminController::class, 'revokeInvitation'])->name('invitations.revoke');
        });
    });
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);
    Route::post('/products', [ApiProductController::class, 'store'])->middleware(['api.admin.auth', 'admin.2fa']);
    Route::get('/products/{id}', [ApiProductController::class, 'show']);
    Route::put('/products/{product}', [ApiProductController::class, 'update'])->middleware(['api.admin.auth', 'admin.2fa']);
    Route::delete('/products/{product}', [ApiProductController::class, 'destroy'])->middleware(['api.admin.auth', 'admin.2fa']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/cart', [ApiCartController::class, 'index']);
        Route::post('/cart', [ApiCartController::class, 'store'])->middleware('throttle:cart-mutation');
        Route::delete('/cart/{id}', [ApiCartController::class, 'destroy'])->middleware('throttle:cart-mutation');

        Route::get('/orders', [ApiOrderController::class, 'index']);
        Route::post('/orders', [ApiOrderController::class, 'store'])->middleware('throttle:checkout');
        Route::get('/orders/{order}', [ApiOrderController::class, 'show']);
    });
});
