# Application Flow - Maat Tech

## User Journey & Application Flow

This document describes the complete flow of data and user interactions through the Maat Tech e-commerce platform, from initial page load through order completion.

---

## 1. Customer User Flows

### 1.1 User Registration Flow

```
┌─ User clicks "Register" ─────────────────────────────────────┐
│                                                               │
├─ GET /register                                               │
│  └─ Display registration form                               │
│     └─ Form Fields:                                          │
│        • Name (required)                                     │
│        • Email (required, unique)                           │
│        • Phone (optional)                                   │
│        • District (optional)                                │
│        • Address (optional)                                 │
│        • Password (required, min 8 chars)                   │
│        • Password Confirmation (required)                   │
│                                                               │
├─ User fills form & clicks "Register"                        │
│                                                               │
├─ POST /register (throttled: 5 per minute)                   │
│  ├─ Validate form data                                      │
│  ├─ Check email uniqueness                                  │
│  ├─ Hash password                                           │
│  ├─ Create User record in database                          │
│  ├─ Generate email verification (optional)                  │
│  └─ Auto-login user                                         │
│                                                               │
├─ CartMergeService.merge()                                    │
│  ├─ Fetch session cart (if exists)                          │
│  ├─ Create/fetch authenticated cart                         │
│  ├─ Merge items                                             │
│  ├─ Fetch session wishlist (if exists)                      │
│  ├─ Create/fetch authenticated wishlist                     │
│  ├─ Merge wishlist items                                    │
│  └─ Clear session data                                      │
│                                                               │
└─ Redirect to dashboard ─────────────────────────────────────┘
```

### 1.2 User Login Flow

```
┌─ User clicks "Login" ─────────────────────────────────────────┐
│                                                                 │
├─ GET /login                                                    │
│  └─ Display login form                                        │
│     └─ Form Fields:                                           │
│        • Email (required)                                     │
│        • Password (required)                                  │
│        • Remember Me (checkbox, optional)                     │
│                                                                 │
├─ User enters credentials & clicks "Login"                      │
│                                                                 │
├─ POST /login (throttled: 5 per minute)                         │
│  ├─ Validate form data                                        │
│  ├─ Find user by email                                        │
│  ├─ Verify password hash                                      │
│  ├─ Check if email is verified (optional)                     │
│  └─ IF credentials invalid:                                   │
│     └─ Return with error message                              │
│                                                                 │
├─ SessionGuard::login(user)                                     │
│  ├─ Create session                                            │
│  ├─ Set session cookie                                        │
│  ├─ Generate remember token (if selected)                     │
│  └─ Regenerate session ID (security)                          │
│                                                                 │
├─ CartMergeService.merge()                                      │
│  ├─ Fetch session cart                                        │
│  ├─ Create/fetch authenticated cart                           │
│  ├─ Merge items from session into authenticated cart          │
│  ├─ Fetch session wishlist                                    │
│  ├─ Merge wishlist items                                      │
│  └─ Clear session data                                        │
│                                                                 │
└─ Redirect to dashboard ─────────────────────────────────────────┘
```

### 1.3 Product Browsing Flow

```
┌─ User lands on site ──────────────────────────────────────────┐
│                                                                 │
├─ GET / (Home Page)                                            │
│  └─ HomeController@index                                      │
│     ├─ Fetch featured products                                │
│     ├─ Fetch categories                                       │
│     └─ Render home view                                       │
│                                                                 │
├─ GET /products (Product Listing)                              │
│  └─ HomeController@products                                   │
│     ├─ Get filter parameters (category, sort)                │
│     ├─ Query products with filters                            │
│     ├─ Paginate results                                       │
│     └─ Render product listing                                 │
│                                                                 │
├─ GET /products/{slug} (Product Detail)                        │
│  └─ HomeController@show                                       │
│     ├─ Find product by slug                                   │
│     ├─ Eager load relationships:                              │
│     │  ├─ Category                                            │
│     │  └─ Reviews                                             │
│     ├─ Calculate averages (ratings)                           │
│     └─ Render product detail page                             │
│        ├─ Product images/videos                               │
│        ├─ Price & discount info                               │
│        ├─ Specifications                                      │
│        ├─ Reviews & ratings                                   │
│        ├─ Stock status                                        │
│        └─ Related products                                    │
│                                                                 │
└─ User decides to add product to cart ───────────────────────────┘
```

### 1.4 Add to Cart Flow (Guest)

```
┌─ User clicks "Add to Cart" ────────────────────────────────────┐
│                                                                 │
├─ POST /cart/add/{product_id}                                   │
│  └─ StorefrontController@addToCart                            │
│     ├─ Find product                                           │
│     ├─ Validate product exists                                │
│     ├─ Get quantity from form (default: 1)                    │
│     └─ Call SessionCartService->add()                         │
│        ├─ Get current session cart: session['cart']           │
│        ├─ IF product_id exists in cart:                       │
│        │  └─ quantity += new quantity                         │
│        │                                                       │
│        └─ ELSE:                                               │
│           ├─ Add new product entry                            │
│           └─ Set quantity                                     │
│                                                                 │
│  └─ Update session: session['cart'] = updated_cart             │
│                                                                 │
├─ IF AJAX request:                                             │
│  └─ Return JSON: {success: true, cart_count: 3}               │
│                                                                 │
├─ ELSE:                                                        │
│  └─ Redirect back with status message                         │
│                                                                 │
└─ Product added to session cart ──────────────────────────────────┘
```

### 1.5 Add to Cart Flow (Authenticated)

```
┌─ User clicks "Add to Cart" ────────────────────────────────────┐
│                                                                 │
├─ POST /cart/add/{product_id} (auth required)                   │
│  └─ StorefrontController@addToCart                            │
│     ├─ Find product                                           │
│     ├─ Validate product exists                                │
│     ├─ Get quantity from form                                 │
│     └─ Call SessionCartService->add()                         │
│        ├─ Also persist to database:                           │
│        │  ├─ Fetch/create Cart for user                       │
│        │  ├─ Find or create CartItem                          │
│        │  ├─ Update quantity                                  │
│        │  └─ Save to database                                 │
│        │                                                       │
│        └─ Update session cart                                 │
│                                                                 │
│  └─ Return response                                           │
│                                                                 │
└─ Product added to user's cart ────────────────────────────────────┘
```

### 1.6 View Cart Flow

```
┌─ User navigates to cart ──────────────────────────────────────┐
│                                                                 │
├─ GET /cart                                                     │
│  └─ StorefrontController@cart                                 │
│     ├─ IF authenticated:                                      │
│     │  └─ Fetch from database:                                │
│     │     ├─ Get user's cart                                  │
│     │     └─ Eager load cart items with products              │
│     │                                                           │
│     └─ IF guest:                                              │
│        └─ Fetch from session:                                 │
│           └─ session['cart']                                  │
│                                                                 │
│  └─ Calculate totals:                                         │
│     ├─ For each item:                                         │
│     │  └─ line_total = product.final_price × quantity        │
│     │                                                           │
│     ├─ subtotal = sum(line_totals)                            │
│     ├─ tax = subtotal × tax_rate (if applicable)              │
│     ├─ shipping = calculate_shipping(items)                   │
│     └─ total = subtotal + tax + shipping                      │
│                                                                 │
│  └─ Render cart view:                                         │
│     ├─ Cart items (product image, name, qty, price)           │
│     ├─ Update quantity buttons                                │
│     ├─ Remove item buttons                                    │
│     ├─ Cart summary (subtotal, tax, total)                    │
│     └─ Proceed to checkout button                             │
│                                                                 │
└─ Cart displayed to user ──────────────────────────────────────┘
```

### 1.7 Update Cart Flow

```
┌─ User changes quantity ────────────────────────────────────────┐
│                                                                 │
├─ POST /cart/update/{product_id}                                │
│  └─ StorefrontController@updateCart                           │
│     ├─ Get new quantity from form                             │
│     ├─ Call SessionCartService->update()                      │
│     │  ├─ Get current session cart                            │
│     │  ├─ Update quantity for product_id                      │
│     │  ├─ IF quantity = 0: remove item                        │
│     │  └─ Update session['cart']                              │
│     │                                                           │
│     └─ IF authenticated:                                      │
│        ├─ Fetch CartItem from database                        │
│        ├─ IF quantity > 0: update quantity                    │
│        └─ ELSE: delete CartItem                               │
│                                                                 │
├─ Redirect back to cart view                                    │
│                                                                 │
└─ Cart updated ────────────────────────────────────────────────┘
```

### 1.8 Checkout Flow

```
┌─ User clicks "Checkout" ──────────────────────────────────────┐
│                                                                 │
├─ GET /checkout                                                 │
│  └─ StorefrontController@checkout                             │
│     ├─ Fetch cart items (session or database)                │
│     ├─ Validate cart not empty                                │
│     ├─ IF empty: redirect with message                        │
│     │                                                           │
│     └─ Display checkout form:                                 │
│        ├─ Shipping Address (if not authenticated)             │
│        ├─ Billing Address (optional)                          │
│        ├─ Payment Method:                                     │
│        │  ├─ Credit Card                                      │
│        │  ├─ Digital Wallet                                   │
│        │  └─ COD (Cash on Delivery)                           │
│        ├─ Special Instructions                                │
│        └─ Order Summary (items + total)                       │
│                                                                 │
├─ User fills form & clicks "Place Order"                        │
│                                                                 │
├─ POST /checkout (throttled: 1 per minute)                     │
│  ├─ Middleware::guest_or_auth (allow both)                    │
│  └─ StorefrontController@placeOrder                           │
│     ├─ Validate form data:                                    │
│     │  ├─ Email (if guest)                                    │
│     │  ├─ Name (required)                                     │
│     │  ├─ Address (required)                                  │
│     │  ├─ City/District (required)                            │
│     │  ├─ Phone (required)                                    │
│     │  └─ Payment method                                      │
│     │                                                           │
│     ├─ IF validation fails:                                   │
│     │  └─ Return with validation errors                       │
│     │                                                           │
│     ├─ Fetch cart items (must not be empty)                  │
│     ├─ Re-validate product stock for each item               │
│     │                                                           │
│     └─ Database::transaction:                                 │
│        ├─ Create Order record:                                │
│        │  ├─ user_id (null if guest)                          │
│        │  ├─ email                                            │
│        │  ├─ total_amount                                     │
│        │  ├─ status = 'pending'                               │
│        │  ├─ payment_method                                   │
│        │  └─ shipping_address (JSON)                          │
│        │                                                       │
│        ├─ For each cart item:                                 │
│        │  ├─ Create OrderItem:                                │
│        │  │  ├─ order_id                                      │
│        │  │  ├─ product_id                                    │
│        │  │  ├─ quantity                                      │
│        │  │  ├─ price (snapshot of product price)             │
│        │  │  └─ discount_amount (snapshot)                    │
│        │  │                                                    │
│        │  └─ Decrement product stock                          │
│        │                                                       │
│        ├─ Clear cart:                                         │
│        │  ├─ Delete CartItems                                 │
│        │  └─ Clear session['cart']                            │
│        │                                                       │
│        └─ Queue async jobs:                                   │
│           ├─ OrderConfirmationJob (send email)                │
│           └─ UpdateInventoryJob (if needed)                   │
│                                                                 │
├─ Redirect to success page                                      │
│  └─ Display:                                                  │
│     ├─ Order confirmation                                     │
│     ├─ Order number                                           │
│     ├─ Total amount                                           │
│     └─ Email confirmation message                             │
│                                                                 │
└─ Order placed successfully ──────────────────────────────────────┘
```

### 1.9 View Orders Flow

```
┌─ Authenticated user clicks "Orders" ──────────────────────────┐
│                                                                 │
├─ GET /orders (auth required)                                   │
│  └─ StorefrontController@orders                               │
│     ├─ Fetch orders for authenticated user:                   │
│     │  └─ Order::where('user_id', auth()->id())               │
│     │     ->orderBy('created_at', 'desc')                     │
│     │     ->with('items.product')                             │
│     │     ->paginate()                                        │
│     │                                                           │
│     └─ Display orders list:                                   │
│        └─ For each order:                                     │
│           ├─ Order number                                     │
│           ├─ Order date                                       │
│           ├─ Total amount                                     │
│           ├─ Status badge                                     │
│           ├─ Item count                                       │
│           └─ View Details link                                │
│                                                                 │
├─ User clicks on specific order                                 │
│  └─ Display order details:                                    │
│     ├─ Order summary (number, date, total)                    │
│     ├─ Shipping address                                       │
│     ├─ Payment method                                         │
│     ├─ Order items (product, qty, price)                      │
│     └─ Status timeline                                        │
│                                                                 │
└─ Orders displayed ────────────────────────────────────────────┘
```

---

## 2. Admin User Flows

### 2.1 Admin Login Flow

```
┌─ Admin visits /admin/login ────────────────────────────────────┐
│                                                                 │
├─ GET /admin/login                                              │
│  └─ AdminController@login                                     │
│     └─ Display login form:                                    │
│        ├─ Admin ID input (format: ADM-XXXX-X)                 │
│        └─ Password input                                      │
│                                                                 │
├─ Admin enters credentials                                      │
│                                                                 │
├─ POST /admin/login (throttled: 5 per minute)                  │
│  └─ AdminController@authenticate                             │
│     ├─ Validate form:                                         │
│     │  ├─ Admin ID format check (regex)                       │
│     │  └─ Password required                                   │
│     │                                                           │
│     ├─ Query Admin by admin_id                                │
│     ├─ Check admin->is_active = true                          │
│     ├─ Verify password hash                                   │
│     │                                                           │
│     └─ IF credentials valid:                                  │
│        ├─ Auth::guard('admin')->login()                       │
│        ├─ Update last_login_at timestamp                      │
│        └─ Regenerate session                                  │
│                                                                 │
│     └─ ELSE:                                                  │
│        └─ Return with error message                           │
│                                                                 │
├─ Redirect to admin dashboard                                   │
│                                                                 │
└─ Admin authenticated ───────────────────────────────────────────┘
```

### 2.2 Admin Dashboard Flow

```
┌─ Admin accesses dashboard ────────────────────────────────────┐
│                                                                 │
├─ GET /admin (admin.auth required)                             │
│  └─ AdminController@dashboard                                │
│     ├─ Fetch current admin                                    │
│     ├─ Query key metrics:                                     │
│     │  ├─ Total orders (count)                                │
│     │  ├─ Total products (count)                              │
│     │  ├─ Total revenue (sum of order amounts)                │
│     │  ├─ Recent orders (last 10)                             │
│     │  └─ Low stock products                                  │
│     │                                                           │
│     └─ Display dashboard:                                     │
│        ├─ Key metrics cards                                   │
│        ├─ Recent orders table                                 │
│        ├─ Product performance chart                           │
│        └─ Quick action buttons                                │
│                                                                 │
└─ Dashboard displayed ──────────────────────────────────────────┘
```

### 2.3 Product Management Flow

#### View All Products
```
GET /admin/products → List products table
├─ Product Name, SKU, Category, Price, Stock, Status
├─ Edit button for each product
└─ Delete button for each product
```

#### Add New Product
```
GET /admin/products/create → Display form

Form Fields:
├─ Category (required)
├─ Name (required)
├─ Slug (auto-generated or manual)
├─ SKU (unique)
├─ Description (WYSIWYG editor)
├─ Price (required)
├─ Compare At Price (optional)
├─ Discount Amount (optional)
├─ Stock Quantity (required)
├─ Specifications (JSON/array)
├─ Image (upload)
├─ Additional Images (multiple upload)
├─ Video Path (URL or upload)
├─ Featured (checkbox)
├─ Status (active/inactive)
├─ SEO Title (optional)
└─ SEO Description (optional)

POST /admin/products
├─ Validate all inputs
├─ Upload image to storage
├─ Create Product record
└─ Redirect with success message
```

#### Edit Product
```
GET /admin/products/{id}/edit → Display form with existing data
├─ Populate all fields with current product info
└─ Submit button = Update

POST /admin/products/{id} (PATCH)
├─ Validate form
├─ Handle image upload (if new)
├─ Update product record
└─ Redirect with success message
```

#### Delete Product
```
DELETE /admin/products/{id}
├─ Validate admin auth
├─ Check if product has orders
├─ If no orders:
│  ├─ Delete product
│  └─ Return success
└─ If has orders:
   ├─ Set status to inactive (soft delete)
   └─ Preserve order history
```

### 2.4 Order Management Flow

```
GET /admin
├─ Fetch all orders (paginated)
├─ Display table:
│  ├─ Order ID, Customer, Total, Status, Actions
│  └─ Filter by status (pending, processing, shipped, delivered)
│
└─ Admin clicks on order
   ├─ GET /admin/orders/{id}
   └─ Display order details:
      ├─ Customer info (name, email, phone)
      ├─ Shipping address
      ├─ Order items (product, qty, price)
      ├─ Payment method & status
      └─ Status update dropdown
         ├─ Pending
         ├─ Processing
         ├─ Shipped
         └─ Delivered

PATCH /admin/orders/{id}
├─ Validate new status
├─ Update order status
├─ Queue notification email
└─ Return success
```

### 2.5 Category Management Flow

```
POST /admin/categories → Create category
├─ Validate form
├─ Create Category record
└─ Return success

PATCH /admin/categories/{id} → Update category
├─ Validate form
├─ Update record
└─ Return success

DELETE /admin/categories/{id} → Delete category
├─ Check if category has products
├─ If no products: delete
└─ If has products: return error or reassign
```

### 2.6 Admin Team Management Flow

```
POST /admin/invitations
├─ Validate email
├─ Create AdminInvitationRequest record
├─ Generate invitation token (future: email)
└─ Return success

POST /admin/invitations/{id}/approve
├─ Find invitation request
├─ Create new Admin record
├─ Set status to active
├─ Generate temporary password
└─ Mark invitation as approved

POST /admin/invitations/{id}/reject
├─ Find invitation request
├─ Update status to rejected
└─ Return success
```

---

## 3. API Request/Response Flows

### 3.1 REST API Product List

```
GET /api/products?page=1&limit=10&category=1

Request Headers:
├─ Accept: application/json
└─ Content-Type: application/json

Response (200 OK):
{
  "data": [
    {
      "id": 1,
      "name": "LED Lamp",
      "slug": "led-lamp",
      "price": 99.99,
      "discount_amount": 10,
      "final_price": 89.99,
      "image": "/storage/products/1.jpg",
      "rating": 4.5,
      "in_stock": true
    }
  ],
  "meta": {
    "pagination": {
      "total": 28,
      "per_page": 10,
      "current_page": 1,
      "last_page": 3
    }
  }
}
```

### 3.2 REST API Add to Cart

```
POST /api/cart

Authorization: Bearer {sanctum_token}

Request Body:
{
  "product_id": 1,
  "quantity": 2
}

Response (201 Created):
{
  "success": true,
  "message": "Item added to cart",
  "cart": {
    "items_count": 5,
    "subtotal": 299.99
  }
}
```

### 3.3 REST API Create Order

```
POST /api/orders

Authorization: Bearer {sanctum_token}

Request Body:
{
  "email": "customer@example.com",
  "name": "John Doe",
  "phone": "1234567890",
  "address": "123 Main St",
  "city": "Dhaka",
  "zip": "1212",
  "payment_method": "cod"
}

Response (201 Created):
{
  "success": true,
  "order": {
    "id": 1,
    "order_number": "ORD-2026-001",
    "total_amount": 340.99,
    "status": "pending",
    "created_at": "2026-07-17T10:30:00Z"
  }
}
```

---

## 4. Data Transformation Flows

### 4.1 Cart to Order Transformation

```
Session/Database Cart
├─ CartItem 1: Product 1, Qty 2
├─ CartItem 2: Product 3, Qty 1
└─ CartItem 3: Product 5, Qty 3

            ↓ (On checkout)

Order Created + OrderItems
├─ Order: Total $340.99, Status pending
├─ OrderItem 1: Product 1, Qty 2, Price $89.99
├─ OrderItem 2: Product 3, Qty 1, Price $149.99
└─ OrderItem 3: Product 5, Qty 3, Price $29.99

            ↓ (After order)

Cart Cleared
├─ Delete CartItems
└─ Clear Session Cart
```

### 4.2 Guest-to-Authenticated Merge

```
Before Login:
├─ Session Cart: {1: 2, 3: 1}
└─ Session Wishlist: [1, 5]

        ↓ (After login via CartMergeService)

After Login:
├─ Database Cart:
│  ├─ CartItem: Product 1, Qty 2
│  └─ CartItem: Product 3, Qty 1
├─ Database Wishlist:
│  ├─ WishlistItem: Product 1
│  └─ WishlistItem: Product 5
└─ Session cleared (cart & wishlist)
```

---

## 5. Event & Notification Flows

### 5.1 Order Confirmation Flow

```
Order Placed Event
    ↓
OrderPlaced Event (queued)
    ↓
OrderConfirmationJob
    ├─ Fetch order details
    ├─ Build email template
    ├─ Send email via SMTP/SendGrid
    └─ Log event
```

### 5.2 Admin Order Update Flow

```
Admin updates order status
    ↓
Order Status Updated
    ↓
Queue: OrderStatusChangedJob
    ├─ Send notification to customer
    ├─ Send notification to admin
    └─ Log change
```

---

## 6. Authentication State Flows

### 6.1 Session Lifecycle (Guest → Authenticated)

```
Guest User
├─ Session created on first visit
├─ session['PHPSESSID'] = generated_id
├─ session['cart'] = {}
├─ session['wishlist'] = []
└─ Anonymous browser tracking

        ↓ (User registers/logs in)

Authenticated User
├─ session['PHPSESSID'] = regenerated_id (security)
├─ session['user'] = User object
├─ Database cart created
├─ Database wishlist created
├─ session['cart'] merged into database
└─ session['wishlist'] merged into database

        ↓ (User logs out)

Guest User (again)
├─ Session destroyed
├─ session cleared
└─ New guest session starts
```

### 6.2 Admin Session Lifecycle

```
Admin Lands on /admin/login
├─ Check admin guard
├─ admin guard not authenticated
└─ Display login form

        ↓ (Admin submits credentials)

Admin Authenticates
├─ Validate credentials
├─ admin guard authenticates
├─ Admin session created
└─ last_login_at updated

        ↓ (Admin accesses protected routes)

Protected Admin Routes
├─ admin.auth middleware checks
├─ admin guard validates
├─ Request proceeds
└─ Response returned

        ↓ (Admin logs out)

Admin Logs Out
├─ admin guard logout
├─ session cleared
└─ Redirect to admin login
```

---

## 7. Error & Exception Flows

### 7.1 Validation Error Flow

```
Form Submitted
    ↓
Validation Fails (e.g., email exists)
    ↓
ExceptionHandler catches ValidationException
    ↓
Redirect back to form with:
├─ Error messages
├─ Old input values (preserved)
└─ Status: 422 Unprocessable Entity
```

### 7.2 Authentication Error Flow

```
Unauthorized Access to Protected Route
    ↓
auth middleware checks Auth::check()
    ↓
User not authenticated
    ↓
Redirect to login page (guest flow)
OR
Return 403 Forbidden (API)
```

### 7.3 Database Error Flow

```
Database Query Fails (e.g., constraint violation)
    ↓
QueryException thrown
    ↓
ExceptionHandler catches it
    ↓
IF production:
├─ Log error
└─ Return generic error message
ELSE:
├─ Display error details
└─ Show SQL query
```

---

## 8. Pagination & Lazy Loading Flows

### 8.1 Product Listing Pagination

```
GET /products?page=2

Response:
├─ 10 products (limit=10)
├─ Current page: 2
├─ Total items: 45
├─ Last page: 5
└─ Pagination links (1, 2, 3, 4, 5)

User clicks page 3
    ↓
GET /products?page=3
    ↓
Next 10 products loaded
```

### 8.2 Order History Pagination

```
GET /orders?page=1

Response:
├─ Recent 15 orders
├─ Pagination meta
└─ Links to other pages

Infinite scroll (optional):
├─ Load first 15 orders
├─ User scrolls to bottom
├─ Auto-load next 15 via AJAX
└─ Append to list
```

---

## 9. Search & Filter Flows

### 9.1 Product Search Flow (Future)

```
User enters search query
    ↓
GET /products/search?q=led+lamp
    ↓
Full-text search on product names & descriptions
    ↓
Return matching products
    ├─ Ordered by relevance
    └─ Paginated
```

### 9.2 Product Filter Flow

```
User selects filter:
├─ Category: "Lighting"
├─ Price: $50 - $150
└─ Rating: 4+ stars

    ↓

GET /products?category=lighting&price_min=50&price_max=150&rating=4
    ↓
Apply WHERE clauses:
├─ category_id IN (...)
├─ price BETWEEN 50 AND 150
└─ (SELECT AVG(rating)) >= 4
    ↓
Return filtered products
```

---

## Document Version
- **Version**: 1.0.0
- **Last Updated**: 2026-07-17
- **Author**: Development Team
- **Status**: Active
