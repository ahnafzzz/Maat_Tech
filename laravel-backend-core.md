# MechArm Lumina — Laravel Backend Specification

## Database Architecture

### 1. Users (Customers)
Standard Laravel users table plus profile metadata.

### 2. Admins (Separate Guard)
| Field | Type | Notes |
|-------|------|-------|
| `id` | bigInt | PK |
| `admin_id` | string | **Required at login** (e.g., ADM-7701-X) |
| `name` | string | |
| `email` | string | unique |
| `password` | string | hashed |
| `is_lead` | boolean | Only one lead admin exists |
| `can_invite` | boolean | Permission flag |
| `status` | enum | active/inactive |
| `timestamps` | | |

### 3. Admin Invitation Requests
For non-lead admins requesting to add someone.

| Field | Type |
|-------|------|
| `id` | PK |
| `requester_admin_id` | FK → admins |
| `target_email` | string |
| `target_name` | string |
| `proposed_admin_id` | string (suggested ID number) |
| `status` | pending/approved/rejected |
| `lead_decision_at` | timestamp nullable |
| `lead_notes` | text nullable |

### 4. Products / Categories
Standard e-commerce schema with tech-focused attributes (specs JSON column).

### 5. Orders / Order Items
For authenticated users only.

### 6. Cart & Wishlist (Authenticated)
Persisted to DB when logged in.

### 7. Guest Session Logic
Uses Laravel Session + encrypted cookie for guests. Merged to DB on login.

---

## Auth Strategy: Dual Guard System

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

---

## Key Business Logic

### Guest Cart/Wishlist Flow
1. **Guest adds item** → Stored in `session('cart')` / `session('wishlist')` as array of `[product_id, qty, added_at]`
2. **Guest leaves** → Session expires, data lost
3. **Guest logs in** → `LoginController` triggers `SessionMergeService`:
   - Read session arrays
   - Upsert into `carts` / `wishlists` tables
   - Clear session keys
4. **Logged-in user** → Always reads from DB tables

### Admin Invitation Flow
1. **Lead Admin** → Direct access to `admin.register` route. Creates admin instantly with auto-generated or custom `admin_id`.
2. **Non-Lead Admin** → Fills "Request Invitation" form (`email`, `name`, `proposed_admin_id`).
 - Creates `AdminInvitationRequest` record with `status: pending`
   - Fires `InvitationRequested` event
3. **Lead Admin Dashboard** → Sees pending requests in `NotificationCenter`
 - `Approve` → Auto-creates admin account, emails credentials - `Reject` → Updates status, optional notes### Admin Login- Route: `/system-panel/login` (separate from customer facing)
- Requires: `admin_id`, `password`
- Uses `Auth::guard('admin')->attempt(['admin_id' => $id, 'password' => $pass])`

---

## Middleware Stack

| Middleware | Purpose |
|------------|---------|
| `RedirectIfNotAdmin` | Protects `/system-panel/*` |
| `LeadAdminOnly` | Blocks non-leads from `admin.register`, `admin.invitations.approve` |
| `MergeCartOnLogin` | Flushes session cart to DB after auth |
| `TrackUserActivity` | Logs login/logout timestamps for customer history |

---

## Route Groups

```php
// routes/web.php

// === CUSTOMER FACING ===
Route::get('/', [HomeController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Guest-safe cart/wishlist actions
Route::post('/cart/add', [CartController::class, 'add']);
Route::get('/cart', [CartController::class, 'index']);
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);

// Auth required customer pages
Route::middleware(['auth:web'])->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index']);
    Route::get('/history', [OrderHistoryController::class, 'index']);
    Route::get('/history/{order}', [OrderHistoryController::class, 'show']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
});

// Customer Auth (Laravel Breeze/UI or custom)
require __DIR__.'/auth.php';

// === SYSTEM PANEL (ADMIN) ===
Route::prefix('system-panel')->name('admin.')->group(function () {
    
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::middleware(['auth:admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // Lead Admin Only
        Route::middleware(['lead.admin'])->group(function () {
            Route::get('/admins/create', [AdminManagementController::class, 'create'])->name('admins.create');
            Route::post('/admins', [AdminManagementController::class, 'store'])->name('admins.store');
            Route::get('/invitations', [AdminInvitationController::class, 'index'])->name('invitations.index');
            Route::post('/invitations/{request}/approve', [AdminInvitationController::class, 'approve'])->name('invitations.approve');
            Route::post('/invitations/{request}/reject', [AdminInvitationController::class, 'reject'])->name('invitations.reject');
        });
        
        // All admins can request
        Route::get('/invitations/create', [AdminInvitationController::class, 'create'])->name('invitations.create');
        Route::post('/invitations', [AdminInvitationController::class, 'store'])->name('invitations.store');
        
        // Product/Order Management (shared)
        Route::resource('products', AdminProductController::class);
        Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
    });
});
```

---

## Models & Relationships

### `Admin` Model Key Features
```php
class Admin extends Authenticatable
{
    protected $fillable = ['admin_id', 'name', 'email', 'password', 'is_lead', 'can_invite', 'status'];
    protected $hidden = ['password', 'remember_token'];
    
    // Auto-format admin_id to uppercase
    public function setAdminIdAttribute($value)
    {
        $this->attributes['admin_id'] = strtoupper($value);
    }
    
    // Relationships
    public function sentInvitations() {
        return $this->hasMany(AdminInvitationRequest::class, 'requester_admin_id');
    }
    public function processedInvitations() {
        return $this->hasMany(AdminInvitationRequest::class, 'processed_by_admin_id');
    }
}
```

### `Cart` Model (DB persisted)
```php
class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id']; // session_id for tracking pre-login
    
    public function items() {
        return $this->hasMany(CartItem::class);
    }
    
    public function user() {
        return $this->belongsTo(User::class);
    }
}
```

---

## Critical Service Classes

### `SessionCartService` (Guest Cart Engine)
```php
class SessionCartService
{
    const CART_KEY = 'mecharm_cart';
    
    public function getItems(): array
    {
        return session()->get(self::CART_KEY, []);
    }
    
    public function add(int $productId, int $qty = 1): void
    {
        $cart = $this->getItems();
        $cart[$productId] = [
            'product_id' => $productId,
            'qty' => ($cart[$productId]['qty'] ?? 0) + $qty,
            'added_at' => now()->toDateTimeString(),
        ];
        session()->put(self::CART_KEY, $cart);
    }
    
    public function remove(int $productId): void
    {
        $cart = $this->getItems();
        unset($cart[$productId]);
        session()->put(self::CART_KEY, $cart);
    }
    
    public function clear(): void
    {
        session()->forget(self::CART_KEY);
    }
    
    public function count(): int
    {
        return collect($this->getItems())->sum('qty');
    }
}
```

### `CartMergeService` (Login Bridge)
```php
class CartMergeService
{
    public function mergeToUser(User $user): void
    {
        $sessionCart = app(SessionCartService::class)->getItems();
        
        if (empty($sessionCart)) return;
        
        $dbCart = Cart::firstOrCreate(['user_id' => $user->id]);
        
        foreach ($sessionCart as $item) {
            CartItem::updateOrCreate(
                ['cart_id' => $dbCart->id, 'product_id' => $item['product_id']],
                ['quantity' => DB::raw("quantity + {$item['qty']}")] // or handle increment properly );
        }
        
        // Recalculate totals, clear session
        app(SessionCartService::class)->clear();
    }
}
```

---

## Admin Blade UI Concept

The admin panel follows the same dark-tech aesthetic:

- **Lead Admin Dashboard**: 
 - Terminal-style statistic cards  - Pending invitation requests table with Approve/Reject action buttons
  - Admin roster with ID badges
  - System activity log (monospace feed)
  
- **Inventory Management**:
  - Data-grid style product table
  - "Schematic upload" for product images
  - JSON spec editor for technical attributes

---

## Implementation Checklist

- [ ] `php artisan make:migration create_admins_table`
- [ ] `php artisan make:migration create_admin_invitation_requests_table`
- [ ] `php artisan make:migration create_products_table` (with JSON specs column)
- [ ] `php artisan make:migration create_carts_table`
- [ ] `php artisan make:migration create_cart_items_table`
- [ ] `php artisan make:migration create_wishlists_table`
- [ ] `php artisan make:migration create_wishlist_items_table`
- [ ] `php artisan make:migration create_orders_table`
- [ ] `php artisan make:migration create_order_items_table`
- [ ] Configure `config/auth.php` dual guards
- [ ] Create `AdminLoginRequest` with `admin_id` validation
- [ ] Seed initial Lead Admin via database seeder
- [ ] Install `laravel/sanctum` if planning API separation later
- [ ] Configure `SESSION_DRIVER` and cookie lifetime for guest carts---

## Security Considerations

1. **Admin ID brute force**: Rate limit `/system-panel/login` by IP + admin_id
2. **Invitation hijacking**: Requests expire after 48 hours
3. **CSRF**: Strict on all state-changing routes
4. **SQL Injection**: Eloquent used throughout; validate `admin_id` format regex `/^ADM-[0-9]{4}-[A-Z]$/`
5. **XSS**: Blade `{{ }}` escaping by default