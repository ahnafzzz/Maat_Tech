# System Architecture - Maat Tech

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Browser     │  │  Mobile      │  │  Static HTML │      │
│  │  (Customer)  │  │  (PWA Ready) │  │  (Admin UI)  │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼──────────────────┼──────────────────┼──────────────┘
          │                  │                  │
          │ HTTP/HTTPS       │ JSON API         │ Form Data
          │                  │                  │
┌─────────▼──────────────────▼──────────────────▼──────────────┐
│                    WEB LAYER (Vite/Tailwind)                │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Static Assets (CSS, JS, Images, Videos)            │   │
│  │  · Tailwind CSS Framework                           │   │
│  │  · Vite Build System                                │   │
│  │  · JavaScript Interactivity                         │   │
│  └──────────────────────────────────────────────────────┘   │
└────────────────────────────┬─────────────────────────────────┘
                             │
                    Blade Templates
                    Form Rendering
                             │
┌────────────────────────────▼─────────────────────────────────┐
│              APPLICATION LAYER (Laravel)                     │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                  ROUTING LAYER                        │  │
│  │  ┌──────────────┬──────────────┬──────────────┐       │  │
│  │  │   Web Routes │  Admin Routes│  API Routes  │       │  │
│  │  └──────┬───────┴──────┬───────┴──────┬───────┘       │  │
│  └─────────┼──────────────┼──────────────┼───────────────┘  │
│            │              │              │                 │
│  ┌─────────▼──────────────▼──────────────▼───────────────┐  │
│  │          MIDDLEWARE LAYER                            │  │
│  │  · Authentication (Auth Guard)                       │  │
│  │  · Authorization (Policies)                          │  │
│  │  · CSRF Protection                                   │  │
│  │  · Rate Limiting                                     │  │
│  │  · Admin Auth Middleware                             │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                     │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │        CONTROLLER LAYER                             │  │
│  │  ┌──────────────┬──────────────┬──────────────┐     │  │
│  │  │   Customer   │    Admin     │     API      │     │  │
│  │  │ Controllers  │ Controllers  │  Controllers │     │  │
│  │  └──────┬───────┴──────┬───────┴──────┬───────┘     │  │
│  └─────────┼──────────────┼──────────────┼─────────────┘  │
│            │              │              │               │
│  ┌─────────▼──────────────▼──────────────▼─────────────┐  │
│  │         SERVICE LAYER                              │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  · CartMergeService                         │  │  │
│  │  │  · SessionCartService                       │  │  │
│  │  │  · Order Processing                         │  │  │
│  │  │  · Email Notifications (queued)             │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  └────────────────────┬─────────────────────────────┘  │
│                       │                                │
│  ┌────────────────────▼─────────────────────────────┐  │
│  │         MODEL LAYER (Eloquent ORM)              │  │
│  │  ┌──────────────┬──────────────────────────┐    │  │
│  │  │   User Models │ Commerce Models         │    │  │
│  │  │  · User       │  · Product              │    │  │
│  │  │  · Admin      │  · Category             │    │  │
│  │  │  · AdminInv.. │  · Cart / CartItem      │    │  │
│  │  │  · Review     │  · Order / OrderItem    │    │  │
│  │  │  · Wishlist   │  · Wishlist/WishlistItem   │    │  │
│  │  └──────────────┴──────────────────────────┘    │  │
│  └────────────────────┬─────────────────────────────┘  │
│                       │                                │
└───────────────────────┼────────────────────────────────┘
                        │ ORM Queries
                        │ Query Builder
                        │
┌───────────────────────▼────────────────────────────────┐
│             DATA ACCESS LAYER                         │
│  ┌────────────────────────────────────────────────┐   │
│  │  · Query Builder                              │   │
│  │  · Migrations System                          │   │
│  │  · Database Transactions                      │   │
│  │  · Relationship Loading                       │   │
│  └────────────────────────────────────────────────┘   │
└───────────────────────┬────────────────────────────────┘
                        │
┌───────────────────────▼────────────────────────────────┐
│          DATABASE LAYER                               │
│  ┌────────────────────────────────────────────────┐   │
│  │  SQLite (Development)                         │   │
│  │  MySQL/PostgreSQL (Production)                │   │
│  │                                                │   │
│  │  Tables:                                       │   │
│  │  · users, admins, admin_invitation_requests  │   │
│  │  · products, categories, reviews             │   │
│  │  · carts, cart_items                         │   │
│  │  · orders, order_items                       │   │
│  │  · wishlists, wishlist_items                 │   │
│  │  · cache, jobs                               │   │
│  └────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────┘
```

---

## 1. Layered Architecture

### 1.1 Client Layer
**Technology**: HTML5, CSS3 (Tailwind), JavaScript, Vite

**Components**:
- Static HTML views (landing, storefront, admin UI prototypes)
- Blade templates (dynamic server-rendered views)
- Responsive CSS using Tailwind CSS
- Client-side JavaScript for interactivity

**Responsibilities**:
- User interface rendering
- Form submission
- Session management (cookies)
- Cart state management (client-side)

---

### 1.2 Web/HTTP Layer
**Technology**: Vite, Tailwind CSS, Blade

**Components**:
- Asset compilation and bundling (Vite)
- CSS framework integration (Tailwind)
- Template rendering engine (Blade)
- Static file serving

**Responsibilities**:
- Asset optimization and caching
- CSS processing and purging
- View compilation
- Static asset delivery

---

### 1.3 Application Layer
**Technology**: Laravel 13, PHP 8.3

**Sub-layers**:

#### Routing Layer
```
Routes → Middleware Stack → Controller → Response
```
- Web routes (server-rendered pages)
- Admin routes (admin-only pages)
- API routes (JSON responses)
- Route model binding
- Named routes for flexibility

#### Middleware Layer
- **Authentication Middleware**: Validates user sessions
- **Admin Auth Guard**: Custom middleware for admin access
- **CSRF Protection**: Prevents cross-site attacks
- **Throttle Middleware**: Rate limiting (auth, admin-login, checkout)
- **Verify CSRF Token**: Form submission protection

#### Controller Layer
Controllers handle business logic orchestration:

**HomeController**
- Display home/landing page
- Display product catalog
- Product detail view

**StorefrontController**
- Cart operations (add, update, remove)
- Checkout flow
- Order placement
- Order history
- Wishlist management
- Customer dashboard

**AdminController**
- Admin authentication
- Product management (CRUD)
- Category management (CRUD)
- Order management (status updates)
- Admin invitation system
- Admin dashboard

**CustomerAuthController**
- Customer registration
- Customer login/logout
- Session management

**Api Controllers** (Product, Cart, Order)
- RESTful JSON endpoints
- API authentication (Sanctum)
- JSON response formatting

#### Service Layer
Business logic isolation:

**CartMergeService**
- Merge guest cart into authenticated cart
- Merge guest wishlist
- Session cleanup after login

**SessionCartService**
- Guest cart storage in session
- Cart item management
- Cart calculations (subtotal, tax, totals)

Other Services (foundation for expansion):
- OrderService (order processing)
- PaymentService (payment integration)
- NotificationService (email/SMS)

#### Model Layer (Eloquent ORM)
Data modeling and relationships:

```
User (Customer Account)
├── orders: hasMany(Order)
└── wishlist: hasOne(Wishlist)

Product
├── category: belongsTo(Category)
└── reviews: hasMany(Review)

Cart
├── user: belongsTo(User)
└── items: hasMany(CartItem)

CartItem
├── cart: belongsTo(Cart)
└── product: belongsTo(Product)

Order
├── user: belongsTo(User)
└── items: hasMany(OrderItem)

OrderItem
├── order: belongsTo(Order)
└── product: belongsTo(Product)

Admin
└── requests: hasMany(AdminInvitationRequest)

Wishlist
├── user: belongsTo(User)
└── items: hasMany(WishlistItem)
```

---

### 1.4 Data Access Layer
**Technology**: Laravel Query Builder, Eloquent ORM

**Components**:
- Query builders for complex queries
- ORM for object-oriented data access
- Migration system for schema management
- Transaction support for multi-step operations
- Relationship eager loading

**Responsibilities**:
- Database abstraction
- Query optimization
- Schema versioning
- Data transaction management

---

### 1.5 Database Layer
**Technology**: SQLite (dev), MySQL (prod)

**Tables**:
```sql
-- Authentication
users (id, name, email, phone, district, address, password, ...)
admins (id, admin_id, password, name, is_active, last_login_at, ...)
admin_invitation_requests (id, admin_id, email, status, ...)

-- Product Catalog
products (id, category_id, name, slug, sku, price, discount_amount, stock, image, video_path, is_featured, ...)
categories (id, name, slug, description, ...)
reviews (id, product_id, user_id, rating, comment, ...)

-- Shopping
carts (id, user_id, session_id, ...)
cart_items (id, cart_id, product_id, quantity, ...)
wishlists (id, user_id, session_id, ...)
wishlist_items (id, wishlist_id, product_id, ...)

-- Orders
orders (id, user_id, total_amount, status, payment_method, ...)
order_items (id, order_id, product_id, quantity, price, ...)

-- Infrastructure
users_cache (for session storage)
jobs (for queue processing)
```

---

## 2. Design Patterns Used

### 2.1 MVC (Model-View-Controller)
- **Model**: Eloquent models with business logic
- **View**: Blade templates and static HTML
- **Controller**: Routes requests to service layer

### 2.2 Service Locator
- `CartMergeService` handles guest-to-auth cart merge
- `SessionCartService` manages session-based cart operations
- Dependency injection via constructor

### 2.3 Repository Pattern
- Eloquent models act as repositories
- Query methods encapsulated in models
- Clean separation between data access and business logic

### 2.4 Factory Pattern
- `UserFactory` for test data generation
- Model factories in tests

### 2.5 Guard Pattern
- Custom `admin.auth` guard for admin authentication
- Separate from default user guard
- Allows multiple authentication contexts

### 2.6 Middleware Pattern
- Authentication middleware for protected routes
- Authorization through middleware
- Composable middleware stack

### 2.7 Observer Pattern
- Model events (creating, created, updating, updated, deleting)
- Audit trail potential (not yet implemented)

---

## 3. Request Flow

### 3.1 Customer Shopping Flow
```
1. Browse Products (HomeController)
   ├── GET / → Display home page
   └── GET /products → List products

2. View Product Details (HomeController)
   └── GET /products/{slug} → Product detail page

3. Add to Cart (StorefrontController)
   ├── POST /cart/add/{product} → Cart service
   ├── Store in session (SessionCartService)
   └── Redirect with status message

4. Checkout Process (StorefrontController)
   ├── GET /checkout → Display cart + checkout form
   ├── POST /checkout (throttled) → PlaceOrder
   │  ├── Validate cart items
   │  ├── Create order record
   │  ├── Create order items
   │  ├── Clear cart
   │  └── Return success
   └── Redirect to orders page

5. View Orders (StorefrontController)
   └── GET /orders → Display order history
```

### 3.2 Admin Flow
```
1. Admin Login (AdminController)
   ├── GET /admin/login → Display login form
   ├── POST /admin/login → Authenticate
   │  ├── Validate credentials
   │  ├── Check admin status
   │  └── Create admin session
   └── Redirect to dashboard

2. Product Management (AdminController)
   ├── GET /admin/products → List products
   ├── POST /admin/products → Create product
   │  ├── Validate input
   │  ├── Upload image/video
   │  ├── Store in database
   │  └── Return success
   ├── PATCH /admin/products/{id} → Update product
   └── DELETE /admin/products/{id} → Delete product

3. Order Management (AdminController)
   ├── GET /admin (dashboard) → View orders overview
   └── PATCH /admin/orders/{id} → Update order status
      ├── Validate order
      ├── Update status
      └── Queue notification email
```

### 3.3 API Flow
```
1. Product API (Api\ProductController)
   ├── GET /api/products → List products (JSON)
   ├── GET /api/products/{id} → Get product (JSON)
   ├── POST /api/products → Create product
   ├── PUT /api/products/{id} → Update product
   └── DELETE /api/products/{id} → Delete product

2. Cart API (Api\CartController)
   ├── GET /api/cart → Get user's cart (auth required)
   ├── POST /api/cart → Add to cart (auth required)
   └── DELETE /api/cart/{id} → Remove item (auth required)

3. Order API (Api\OrderController)
   ├── GET /api/orders → List user's orders (auth required)
   ├── POST /api/orders → Create order (auth required)
   └── GET /api/orders/{id} → Get order details (auth required)
```

---

## 4. Authentication Architecture

### 4.1 Customer Authentication
```
Register/Login → SessionGuard → User Model
                   ↓
            Session Cookie
                   ↓
            Remember Token (optional)
                   ↓
            Protected Routes (auth middleware)
```

### 4.2 Admin Authentication
```
Login with Admin ID → CustomGuard (admin.auth)
      ↓
Admin Model Lookup
      ↓
Password Hash Verification
      ↓
Session Creation
      ↓
Protected Routes (admin.auth middleware)
```

### 4.3 API Authentication
```
Sanctum Token → Token Guard
      ↓
API Route Protection (auth:sanctum)
      ↓
Request validation
```

---

## 5. Data Flow

### 5.1 Cart Flow
```
Guest User
├── Browse Products
├── Add to Cart
│  └── Store in session['cart']
├── Login
│  └── CartMergeService
│     ├── Fetch session cart
│     ├── Create/fetch authenticated cart
│     ├── Merge items
│     └── Clear session cart
└── Proceed to Checkout

Authenticated User
├── Browse Products
├── Add to Cart
│  ├── Store in database (Cart/CartItem)
│  └── Optionally sync to session
└── Proceed to Checkout
```

### 5.2 Order Flow
```
Customer Places Order
├── Validate cart
├── Create Order record
├── Create OrderItems from cart
├── Clear/archive cart
├── Send confirmation email (queued)
├── Return success
└── Customer views in order history

Admin Updates Order Status
├── Authenticate admin
├── Validate order
├── Update status
├── Queue notification email
└── Broadcast update
```

---

## 6. Caching Strategy

### 6.1 Database Caching
- **Driver**: Database (configurable to Redis)
- **Tables**: 
  - `cache` for generic cache entries
  - `cache_locks` for distributed locking

### 6.2 Cache Keys
```
product:{id}          - Product data
product:featured      - Featured products
category:{id}         - Category data
cart:{session_id}     - Guest cart
orders:user:{id}      - User's orders
```

### 6.3 Cache Invalidation
- Manual cache clear on product/category updates
- Automatic expiration after TTL

---

## 7. Session Management

### 7.1 Session Driver
- **Driver**: Database (can switch to file or Redis)
- **Table**: `sessions` (auto-created)
- **Lifetime**: 120 minutes (configurable)

### 7.2 Session Data Structure
```php
session = [
    'PHPSESSID' => '...',
    'cart' => [
        '1' => 2,      // product_id => quantity
        '3' => 1,
    ],
    'wishlist' => [1, 3, 5],
    'user' => User object
]
```

---

## 8. Queue Architecture (Foundation)

### 8.1 Queue Driver
- **Driver**: Database (configurable to Redis, SQS)
- **Table**: `jobs` (auto-created)

### 8.2 Queued Jobs
```
OrderConfirmationJob
├── Input: Order ID
├── Action: Send confirmation email
└── Retry: 3 times

OrderShippedNotificationJob
├── Input: Order ID, Tracking info
├── Action: Send shipping notification
└── Retry: 3 times
```

---

## 9. File Storage Architecture

### 9.1 Local Storage
```
storage/
├── app/
│  ├── products/
│  │  ├── images/
│  │  └── videos/
│  ├── uploads/
│  └── temp/
├── framework/
│  ├── cache/
│  └── sessions/
└── logs/
   └── laravel.log
```

### 9.2 Public Storage
```
public/
├── images/
├── storage/ → symlink to storage/app/public
└── build/
   ├── app.css
   └── app.js
```

---

## 10. Error Handling Architecture

### 10.1 Exception Handling
```
ExceptionHandler (app/Exceptions)
├── Validation Exceptions
│  └── Render validation errors
├── Authentication Exceptions
│  └── Redirect to login
├── Authorization Exceptions
│  └── 403 Forbidden
├── Database Exceptions
│  └── Log and return generic error
└── HTTP Exceptions
   └── Return appropriate status codes
```

### 10.2 Logging
- **Channel**: Stack (file + single)
- **Level**: Debug (development), Notice (production)
- **Files**: `storage/logs/laravel.log`

---

## 11. Security Architecture

### 11.1 CSRF Protection
```
POST/PATCH/DELETE requests
├── Require CSRF token
├── Token stored in session
├── Token attached to form
└── Verified by middleware
```

### 11.2 Password Security
```
User Input → Hash (bcrypt) → Database Storage
Verification: Input → Hash → Compare with stored
```

### 11.3 SQL Injection Prevention
```
All queries → Eloquent ORM → Parameterized queries
No raw SQL strings
```

### 11.4 Session Security
- Secure cookies (HTTPS only in production)
- HttpOnly flag prevents JavaScript access
- Session regeneration on login
- PHPSESSID as session identifier

---

## 12. Scalability Considerations

### 12.1 Horizontal Scaling
- Stateless application design
- Database as single source of truth
- Session stored in database (shareable)
- Queue system for async operations

### 12.2 Database Scaling
- Read replicas for reporting
- Write master for transactional operations
- Connection pooling
- Query optimization with indexes

### 12.3 Caching Layers
- Redis for cache layer (future)
- CDN for static assets (future)
- Browser caching for client assets

---

## 13. Technology Integration Points

### 13.1 Payment Gateway (Future)
```
Order Placement → Payment Service
├── Bkash integration
├── SSLCommerz integration
└── Stripe integration
   └── Update order payment status
```

### 13.2 Email Service (Future)
```
Event (OrderPlaced) → Queue Job → Email Service
├── SMTP (local development)
├── SendGrid (production)
└── Mail templates (Blade)
```

### 13.3 SMS Notifications (Future)
```
Order Event → SMS Service
├── Twilio integration
└── Order status updates
```

---

## 14. Deployment Architecture

### 14.1 Local Development
```
Developer Machine
├── PHP 8.3+ CLI
├── SQLite database
├── Vite dev server
└── Laravel dev server
```

### 14.2 Production Stack
```
Client → CDN → Load Balancer
              ├── Web Server 1 (Nginx)
              ├── Web Server 2 (Nginx)
              └── Web Server 3 (Nginx)
                   ↓
              Database (MySQL)
                   ├── Primary (write)
                   └── Replica (read)
              Cache (Redis)
              Queue Worker
              Job Scheduler
```

---

## Document Version
- **Version**: 1.0.0
- **Last Updated**: 2026-07-17
- **Author**: Development Team
- **Status**: Active
