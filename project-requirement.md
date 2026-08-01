# Project Requirements - Maat Tech

## Project Overview
**Project Name**: Maat Tech / MECHARM Prototype  
**Type**: E-Commerce Lighting Storefront with Admin Management  
**Current Status**: Beta / Full Laravel Implementation Phase  
**Version**: 1.0.0

---

## 1. Business Objectives

### Primary Goals
- Build a premium lighting product e-commerce platform focusing on **MechArm Lumina** product line
- Enable seamless customer shopping experience with product browsing, cart management, and checkout
- Provide admin team with comprehensive dashboard for inventory, orders, and user management
- Create scalable architecture supporting future expansion to multiple business verticals

### Target Audience
- **Primary**: End customers seeking premium lighting solutions
- **Secondary**: Admin/Lead users managing inventory and orders
- **Tertiary**: System administrators overseeing platform health

---

## 2. Functional Requirements

### 2.1 Customer Features

#### Authentication & Account Management
- [x] Customer registration with email validation
- [x] Secure login/logout with session management
- [x] Customer dashboard displaying order history and account details
- [x] User profile management (name, email, phone, address, district)
- [x] Password reset capability

#### Product Browsing
- [x] Product catalog with categories
- [x] Product detail pages with images, videos, specifications
- [x] Product filtering by category
- [x] Product search functionality (planned)
- [x] Product reviews and ratings (model ready)
- [x] Featured products display
- [x] Product pricing with discount display

#### Shopping Cart & Wishlist
- [x] Add products to cart
- [x] Update cart item quantities
- [x] Remove items from cart
- [x] Cart persistence (session + database)
- [x] Guest-to-authenticated cart merge on login
- [x] Wishlist management
- [x] Quick add to cart from product listings

#### Checkout & Orders
- [x] Checkout process with order summary
- [x] Order placement with customer validation
- [x] Order history and tracking
- [x] Order status updates
- [x] COD (Cash on Delivery) support (base structure)
- [x] Order confirmation and notifications (email structure in place)

---

### 2.2 Admin Features

#### Authentication
- [x] Secure admin login with unique Admin ID format (ADM-XXXX-X)
- [x] Password-based authentication
- [x] Session management with timeout
- [x] Admin logout
- [x] Admin invitation system for team expansion

#### Product Management
- [x] Add new products with complete details
- [x] Edit product information (name, price, discount, specs, images)
- [x] Delete products with validation
- [x] Upload product images and videos
- [x] Manage product variants
- [x] Set product discounts and compare-at prices
- [x] Manage product status (active/inactive)
- [x] SEO optimization fields (title, description)

#### Inventory Management
- [x] Track product stock levels
- [x] Update stock on order placement
- [x] Low stock alerts (structure in place)
- [x] Inventory history tracking (foundation ready)

#### Category Management
- [x] Create product categories
- [x] Update category details
- [x] Delete categories with product reassignment
- [x] Organize product taxonomy

#### Order Management
- [x] View all customer orders
- [x] Update order status (pending, processing, shipped, delivered)
- [x] Order details with customer information
- [x] Order item breakdown
- [x] Payment status tracking

#### Team Management
- [x] Send admin invitations
- [x] Approve/reject admin requests
- [x] View admin activity and last login
- [x] Manage admin status (active/inactive)

#### Analytics (Foundation)
- [x] Dashboard overview with key metrics
- [x] Order and revenue statistics
- [x] Product performance data

---

## 3. Non-Functional Requirements

### Performance
- Page load time: < 2 seconds
- API response time: < 500ms
- Database query optimization with indexing
- Caching strategy for frequently accessed data

### Security
- [ ] HTTPS/SSL encryption in production
- [x] CSRF protection (Laravel built-in)
- [x] SQL injection prevention (Eloquent ORM)
- [x] Password hashing (bcrypt)
- [x] Input validation and sanitization
- [x] Admin authentication guard
- [x] Session security
- [ ] Rate limiting (implemented for auth endpoints)
- [ ] Payment gateway security (ready for integration)

### Scalability
- SQLite for development, MySQL/PostgreSQL ready for production
- Stateless API design for horizontal scaling
- Database migration system for schema updates
- Queue system for async operations (email, notifications)

### Reliability
- Database backups strategy
- Error logging and monitoring
- Graceful error handling
- Transaction support for order placement

### Maintainability
- MVC architecture with clear separation of concerns
- Service-oriented architecture (CartService, CartMergeService)
- Middleware for cross-cutting concerns
- Comprehensive code documentation

---

## 4. Technical Constraints

### Required Stack
- PHP 8.3+
- Laravel 13+
- SQLite (dev) / MySQL (production)
- Vite for asset bundling
- Tailwind CSS for styling

### Browser Compatibility
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

### Database Support
- Minimum: SQLite for development
- Production: MySQL 8.0+ or PostgreSQL 12+
- All tables support foreign key constraints

---

## 5. Current Implementation Status

### ✅ Completed
- Core Laravel structure with 13 models
- Authentication system (Customer + Admin)
- Product catalog with relationships
- Cart management system (session + database)
- Order workflow
- Admin dashboard foundation
- Static prototypes (HTML/CSS/JS)
- Database migrations and seeders

### 🔄 In Progress / Planned
- [ ] Payment gateway integration (Bkash, SSLCommerz, Stripe)
- [ ] Email notification system
- [ ] Advanced product search (full-text)
- [ ] Product reviews and ratings UI
- [ ] Advanced analytics dashboard
- [ ] Shipping zone management
- [ ] Pathao integration
- [ ] Coupon and discount system
- [ ] Admin panel (Filament integration)
- [ ] API documentation (Swagger/OpenAPI)
- [ ] Automated testing suite
- [ ] CDN integration for media
- [ ] Admin activity logging

### 🔮 Future Enhancements
- Multi-warehouse support
- Subscription products
- Gift cards
- Affiliate program
- Customer segmentation
- Personalized recommendations
- Mobile app (native)

---

## 6. Data Entities

### Core Models
| Model | Purpose | Status |
|-------|---------|--------|
| User | Customer accounts | ✅ Active |
| Product | Product catalog | ✅ Active |
| Category | Product organization | ✅ Active |
| Cart | Shopping carts | ✅ Active |
| CartItem | Cart line items | ✅ Active |
| Order | Customer orders | ✅ Active |
| OrderItem | Order line items | ✅ Active |
| Admin | Admin accounts | ✅ Active |
| AdminInvitationRequest | Team expansion | ✅ Active |
| Review | Product reviews | ✅ Active |
| Wishlist | Saved items | ✅ Active |
| WishlistItem | Wishlist entries | ✅ Active |

---

## 7. API Endpoints

### Public Endpoints
```
GET    /products                 - List all products
GET    /products/{id}            - Get product details
GET    /                         - Home page
POST   /login                    - Customer login
POST   /register                 - Customer registration
```

### Authenticated Customer Endpoints
```
GET    /dashboard                - Customer dashboard
GET    /cart                     - View cart
POST   /cart/add/{product}       - Add to cart
POST   /cart/update/{product}    - Update cart
POST   /cart/remove/{product}    - Remove from cart
POST   /checkout                 - Place order
GET    /orders                   - View orders
GET    /wishlist                 - View wishlist
POST   /wishlist/{product}       - Toggle wishlist
```

### Admin Endpoints
```
POST   /admin/login              - Admin authentication
GET    /admin                    - Admin dashboard
GET    /admin/products           - Product list
POST   /admin/products           - Create product
PATCH  /admin/products/{id}      - Update product
DELETE /admin/products/{id}      - Delete product
POST   /admin/categories         - Create category
PATCH  /admin/categories/{id}    - Update category
DELETE /admin/categories/{id}    - Delete category
PATCH  /admin/orders/{id}        - Update order status
POST   /admin/invitations        - Request invitation
POST   /admin/invitations/{id}/approve  - Approve invitation
POST   /admin/invitations/{id}/reject   - Reject invitation
```

### REST API Endpoints
```
GET    /api/products             - List products (JSON)
POST   /api/products             - Create product
GET    /api/products/{id}        - Get product (JSON)
PUT    /api/products/{id}        - Update product
DELETE /api/products/{id}        - Delete product
GET    /api/cart                 - Get cart (auth required)
POST   /api/cart                 - Add to cart (auth required)
DELETE /api/cart/{id}            - Delete cart item (auth required)
GET    /api/orders               - List orders (auth required)
POST   /api/orders               - Create order (auth required)
GET    /api/orders/{id}          - Get order (auth required)
```

---

## 8. Success Metrics

### User Metrics
- Customer registration rate
- Cart abandonment rate
- Conversion rate (visitor to order)
- Average order value
- Customer retention rate

### Admin Metrics
- Order processing time
- Product catalog growth
- Admin action response time
- System uptime

### Technical Metrics
- Page load time < 2s
- API response time < 500ms
- 99.5% uptime
- Error rate < 0.5%

---

## 9. Deployment Requirements

### Development
- Local PHP 8.3+ environment
- SQLite database
- Node.js for frontend build
- Laravel development server

### Staging/Production
- Linux server (CachyOS/Ubuntu recommended)
- PHP 8.3+ with required extensions
- MySQL 8.0+ database
- Nginx/Apache web server
- SSL/TLS certificate
- Email service (SMTP/SendGrid)
- CDN for static assets
- Backup strategy (daily, encrypted)

### Hosting Options
- Netlify (static frontend)
- Vercel (static frontend)
- AWS/DigitalOcean/Heroku (full stack)
- Laravel Forge/Envoyer (Laravel specific)

---

## 10. Documentation & Support

### Required Documentation
- [x] User Guide (USER_GUIDE.md)
- [x] Architecture Design (architecture.md)
- [ ] API Documentation
- [ ] Admin Manual
- [ ] Developer Setup Guide
- [ ] Database Schema Diagram

### Support Channels
- Bug reporting system
- Feature request tracking
- Admin support email

---

## 11. Timeline & Milestones

### Phase 1: Foundation (Complete)
- ✅ Laravel setup and structure
- ✅ Database schema design
- ✅ Core models and migrations
- ✅ Authentication system

### Phase 2: Core Features (In Progress)
- 🔄 Product management
- 🔄 Cart and checkout
- 🔄 Order management
- 🔄 Basic admin dashboard

### Phase 3: Enhancement
- ⏳ Payment integration
- ⏳ Email notifications
- ⏳ Advanced analytics
- ⏳ Filament admin panel

### Phase 4: Production Ready
- ⏳ Performance optimization
- ⏳ Security hardening
- ⏳ Load testing
- ⏳ Production deployment

---

## Document Version
- **Version**: 1.0.0
- **Last Updated**: 2026-07-17
- **Author**: Development Team
- **Status**: Active


