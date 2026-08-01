# Design Document - Maat Tech

## Design Overview

This document outlines the UI/UX design, design patterns, component architecture, and visual guidelines for the Maat Tech e-commerce platform.

---

## 1. Design Philosophy

### 1.1 Core Principles
- **Premium Positioning**: Design reflects high-end lighting products (MechArm Lumina)
- **Clean & Minimal**: Reduce cognitive load, focus on products
- **Responsive Design**: Seamless experience across all devices
- **Accessibility**: WCAG 2.1 AA compliance
- **Performance**: Fast load times, optimized images
- **Consistency**: Unified design language across all interfaces

### 1.2 Target Audience Design
| Audience | Design Approach |
|----------|-----------------|
| Premium Customers | Elegant, spacious layouts; high-quality imagery |
| Mobile Users | Touch-friendly, thumb-accessible controls |
| Admins | Efficiency-focused, data-dense layouts |
| Accessibility Users | Clear hierarchy, keyboard navigation |

---

## 2. Visual Design System

### 2.1 Color Palette

#### Primary Colors
```
Primary Brand Color:  #000000 (Black)
Usage: Headers, buttons, accents

Secondary Color:      #FFFFFF (White)
Usage: Backgrounds, text contrast

Tertiary Color:       #F5F5F5 (Light Gray)
Usage: Backgrounds, card containers
```

#### Accent Colors
```
Success:     #10B981 (Green) - Order confirmation, success states
Warning:     #F59E0B (Amber) - Low stock, alerts
Danger:      #EF4444 (Red)   - Errors, deletions
Info:        #3B82F6 (Blue)  - Information, links
Neutral:     #6B7280 (Gray)  - Secondary text, disabled states
```

#### Premium Product Showcase
```
Gold Accent:         #D97706 (For premium/featured products)
Deep Charcoal:       #1F2937 (For depth and contrast)
Premium Background:  #0F172A (Navy) - For hero sections
```

### 2.2 Typography

#### Font Stack
```
Primary Font:   "Inter", "Helvetica Neue", sans-serif
              - Modern, clean, highly legible
              - Used for body text, UI elements

Headings:       "Poppins", "Inter", sans-serif
              - Bold, contemporary
              - Used for titles, section headers

Monospace:      "Fira Code", "Courier New", monospace
              - Product SKUs, order IDs
```

#### Type Scale
```
H1 (Hero):       48px / 1.2 line-height / 700 weight - Page titles
H2 (Major):      36px / 1.25 line-height / 600 weight - Section headers
H3 (Medium):     24px / 1.35 line-height / 600 weight - Subsections
H4 (Minor):      20px / 1.4 line-height / 500 weight - Component headers
Body Large:      18px / 1.6 line-height / 400 weight - Important body text
Body Regular:    16px / 1.6 line-height / 400 weight - Standard body text
Body Small:      14px / 1.5 line-height / 400 weight - Secondary text
Caption:         12px / 1.5 line-height / 500 weight - Labels, captions
Label:           12px / 1.4 line-height / 600 weight - Form labels
```

### 2.3 Spacing System

Using 4px base unit (Tailwind standard):
```
xs:      4px (spacing-1)   - Tight spacing
sm:      8px (spacing-2)   - Small spacing
md:      16px (spacing-4)  - Default spacing
lg:      24px (spacing-6)  - Large spacing
xl:      32px (spacing-8)  - Extra large
2xl:     48px (spacing-12) - 2x extra large
3xl:     64px (spacing-16) - 3x extra large
```

### 2.4 Component Sizes

#### Buttons
```
Small:    32px height, 12px-16px padding
Medium:   40px height, 16px-24px padding (default)
Large:    48px height, 20px-32px padding
```

#### Form Fields
```
Input Height:      40px
Input Padding:     12px horizontal, 8px vertical
Border Radius:     6px (subtle rounding)
Focus Outline:     3px offset, 2px width
```

#### Cards
```
Border Radius:     8px
Box Shadow:        0 1px 3px rgba(0,0,0,0.1) - rest
                   0 4px 6px rgba(0,0,0,0.1) - hover
Padding:           16px (small), 24px (medium), 32px (large)
```

### 2.5 Shadows & Elevation

```
Elevation 1 (Card):      0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)
Elevation 2 (Hover):     0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06)
Elevation 3 (Modal):     0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)
Elevation 4 (Dropdown):  0 20px 25px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.04)
```

### 2.6 Border Radius

```
None:      0px
xs:        2px
sm:        4px
md:        6px (default)
lg:        8px
xl:        12px
2xl:       16px
full:      9999px (pills, circles)
```

---

## 3. UI Component Library

### 3.1 Buttons

#### Button States
```html
<!-- Primary Button -->
<button class="bg-black text-white px-6 py-2 rounded-md hover:bg-gray-900 active:bg-gray-950">
  Add to Cart
</button>

<!-- Secondary Button -->
<button class="border border-gray-300 text-gray-900 px-6 py-2 rounded-md hover:bg-gray-50">
  Cancel
</button>

<!-- Danger Button -->
<button class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700">
  Delete
</button>

<!-- Disabled Button -->
<button disabled class="bg-gray-300 text-gray-500 px-6 py-2 rounded-md cursor-not-allowed">
  Checkout
</button>
```

#### Button Variants
- **Primary**: Solid black, highest contrast
- **Secondary**: Outlined, medium emphasis
- **Tertiary**: Ghost/text button, lowest emphasis
- **Danger**: Red background for destructive actions
- **Success**: Green background for confirmations
- **Loading**: Spinner inside button during submission

### 3.2 Cards

#### Product Card
```html
<div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
  <!-- Product Image -->
  <div class="relative overflow-hidden bg-gray-100 h-64">
    <img src="product.jpg" alt="Product" class="w-full h-full object-cover">
    <span class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-md text-sm">
      -20%
    </span>
  </div>
  
  <!-- Product Info -->
  <div class="p-4">
    <h3 class="text-lg font-semibold text-gray-900">Product Name</h3>
    <p class="text-sm text-gray-600 mt-1">Brief description</p>
    
    <!-- Rating -->
    <div class="flex items-center mt-3 gap-1">
      <span class="text-yellow-400">★★★★★</span>
      <span class="text-sm text-gray-600">(42 reviews)</span>
    </div>
    
    <!-- Pricing -->
    <div class="flex items-baseline gap-2 mt-3">
      <span class="text-xl font-bold text-gray-900">$99.99</span>
      <span class="text-sm text-gray-500 line-through">$149.99</span>
    </div>
    
    <!-- Action -->
    <button class="w-full mt-4 bg-black text-white py-2 rounded-md hover:bg-gray-900">
      Add to Cart
    </button>
  </div>
</div>
```

#### Order Card
```html
<div class="border border-gray-200 rounded-lg p-6">
  <div class="flex justify-between items-start mb-4">
    <div>
      <p class="text-sm text-gray-600">Order #ORD-2026-001</p>
      <p class="text-lg font-semibold mt-1">$299.99</p>
    </div>
    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
      Delivered
    </span>
  </div>
  <p class="text-sm text-gray-600">Ordered on July 15, 2026</p>
</div>
```

### 3.3 Forms

#### Input Field
```html
<div class="mb-6">
  <label for="email" class="block text-sm font-medium text-gray-900 mb-2">
    Email Address
  </label>
  <input 
    type="email" 
    id="email" 
    placeholder="you@example.com"
    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
  />
</div>
```

#### Select Dropdown
```html
<div class="mb-6">
  <label for="category" class="block text-sm font-medium text-gray-900 mb-2">
    Category
  </label>
  <select 
    id="category" 
    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-black"
  >
    <option value="">Select a category</option>
    <option value="1">Lighting</option>
    <option value="2">Fixtures</option>
  </select>
</div>
```

#### Checkbox
```html
<label class="flex items-center gap-3 cursor-pointer">
  <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black" />
  <span class="text-sm text-gray-700">I agree to the terms</span>
</label>
```

#### Error State
```html
<div class="mb-6">
  <label for="password" class="block text-sm font-medium text-gray-900 mb-2">
    Password
  </label>
  <input 
    type="password" 
    id="password" 
    class="w-full px-4 py-2 border-2 border-red-500 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
  />
  <p class="mt-1 text-sm text-red-600">Password must be at least 8 characters</p>
</div>
```

### 3.4 Navigation

#### Header Navigation
```html
<header class="bg-white border-b border-gray-200 sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
    <!-- Logo -->
    <a href="/" class="text-xl font-bold text-gray-900">
      Maat Tech
    </a>
    
    <!-- Main Nav -->
    <nav class="hidden md:flex gap-8 items-center">
      <a href="/products" class="text-gray-700 hover:text-gray-900">Products</a>
      <a href="/about" class="text-gray-700 hover:text-gray-900">About</a>
      <a href="/contact" class="text-gray-700 hover:text-gray-900">Contact</a>
    </nav>
    
    <!-- Actions -->
    <div class="flex gap-4 items-center">
      <a href="/cart" class="relative">
        <span class="text-2xl">🛒</span>
        <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
          3
        </span>
      </a>
      <a href="/account" class="text-gray-700 hover:text-gray-900">Account</a>
    </div>
  </div>
</header>
```

#### Breadcrumb Navigation
```html
<nav class="mb-6 text-sm flex items-center gap-2">
  <a href="/" class="text-blue-600 hover:underline">Home</a>
  <span class="text-gray-400">/</span>
  <a href="/products" class="text-blue-600 hover:underline">Products</a>
  <span class="text-gray-400">/</span>
  <span class="text-gray-900">LED Lights</span>
</nav>
```

### 3.5 Notifications & Alerts

#### Success Toast
```html
<div class="fixed top-4 right-4 bg-green-50 border border-green-200 rounded-lg p-4 flex gap-3">
  <span class="text-green-600">✓</span>
  <p class="text-green-800">Product added to cart successfully!</p>
</div>
```

#### Error Alert
```html
<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
  <p class="text-red-800 font-medium">Error</p>
  <p class="text-red-700 text-sm mt-1">Unable to process your request. Please try again.</p>
</div>
```

#### Info Banner
```html
<div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
  <p class="text-blue-900 text-sm">
    <strong>Free shipping:</strong> On orders over $100 (limited time offer)
  </p>
</div>
```

---

## 4. Layout Patterns

### 4.1 Page Layouts

#### Full-Width Container
```html
<div class="min-h-screen bg-gray-50">
  <header><!-- Navigation --></header>
  <main class="max-w-7xl mx-auto px-4 py-8">
    <!-- Page Content -->
  </main>
  <footer><!-- Footer --></footer>
</div>
```

#### Sidebar Layout
```html
<div class="flex min-h-screen">
  <aside class="w-64 bg-white border-r border-gray-200">
    <!-- Admin Menu -->
  </aside>
  <main class="flex-1">
    <!-- Content -->
  </main>
</div>
```

#### Grid Layout
```html
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
  <!-- Product Cards -->
</div>
```

### 4.2 Responsive Breakpoints

Using Tailwind CSS breakpoints:
```
sm:  640px   - Small devices
md:  768px   - Tablets
lg:  1024px  - Desktop
xl:  1280px  - Large desktop
2xl: 1536px  - Extra large desktop
```

Example:
```html
<!-- 1 column on mobile, 2 on tablets, 3 on desktop -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <!-- Items -->
</div>
```

---

## 5. Page-Specific Designs

### 5.1 Home / Landing Page

#### Hero Section
```
- Full viewport height
- Background: Hero image with overlay
- Text: "Premium Lighting Solutions"
- CTA Button: "Shop Now"
- Animation: Subtle parallax scroll effect
```

#### Featured Products Section
```
- Section header: "Featured Lighting"
- Grid: 4 product cards (responsive)
- Each card: Image, name, rating, price, action button
```

#### Newsletter Signup
```
- Dark background
- Heading: "Stay Updated"
- Email input + Subscribe button
```

### 5.2 Product Listing Page

#### Layout
```
┌─────────────────────────────────────┐
│         Filters Sidebar              │ Main Content
├─────────────────────────────────────┤
│ • Category                           │ Product Grid
│ • Price Range                        │ (Responsive)
│ • Rating                             │
│ • Brand                              │
│ • Sort Options                       │
└─────────────────────────────────────┘
```

#### Filter Options
- Category (checkbox)
- Price range (slider)
- Rating (star filter)
- Sort (dropdown: "Newest", "Price: Low to High", "Popular")

### 5.3 Product Detail Page

#### Layout
```
┌─────────────────────────────────────┐
│ Product Images (Main + Thumbnails)  │ Product Info
├─────────────────────────────────────┤ • Name
│                                      │ • Rating & Reviews
│         Image Carousel               │ • Price & Discount
│                                      │ • Description
└─────────────────────────────────────┘ • Variants
                                        • Quantity + Add to Cart
                                        • Wishlist
                                        • Share
```

### 5.4 Shopping Cart

#### Cart Items Table
```
Product | Quantity | Price | Line Total | Action
```

#### Cart Summary
```
Subtotal:    $299.99
Shipping:    $10.00
Tax:         $31.00
─────────────────────
Total:       $340.99
```

### 5.5 Checkout Page

#### Multi-Step Process
```
Step 1: Shipping Address
├── Form: Name, Email, Address, City, ZIP
└── Next Button

Step 2: Shipping Method
├── Options: Standard (5-7 days), Express (2-3 days), Overnight
└── Next Button

Step 3: Payment Method
├── Option 1: Credit Card
├── Option 2: Digital Wallet
├── Option 3: COD
└── Place Order Button
```

### 5.6 Admin Dashboard

#### Dashboard Overview
```
┌─────────────────────────────────────┐
│ Key Metrics (Cards)                 │
│ • Total Orders: 342                 │
│ • Revenue: $45,230                  │
│ • Products: 28                      │
│ • Customers: 156                    │
└─────────────────────────────────────┘

Recent Orders (Table)
├── Order ID | Customer | Amount | Status | Action

Top Products (Chart)
```

#### Admin Sidebar Navigation
```
Dashboard
├── Products
│  ├── All Products
│  └── Add New
├── Orders
│  ├── All Orders
│  └── Order Details
├── Categories
│  ├── All Categories
│  └── Add New
├── Customers
│  ├── All Customers
│  └── Customer Details
├── Team
│  ├── Admins
│  └── Invitations
└── Settings
   ├── General
   └── Security
```

---

## 6. Interaction Patterns

### 6.1 Loading States

```html
<!-- Skeleton Loading -->
<div class="animate-pulse">
  <div class="h-48 bg-gray-300 rounded-lg mb-4"></div>
  <div class="h-6 bg-gray-300 rounded w-3/4 mb-2"></div>
  <div class="h-4 bg-gray-300 rounded w-1/2"></div>
</div>

<!-- Spinner -->
<div class="flex justify-center">
  <div class="animate-spin w-8 h-8 border-4 border-gray-300 border-t-black rounded-full"></div>
</div>
```

### 6.2 Hover Effects

```css
/* Button Hover */
button:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Link Hover */
a:hover {
  text-decoration: underline;
  color: darken(primary);
}

/* Card Hover */
.card:hover {
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  transform: translateY(-4px);
}
```

### 6.3 Transition Effects

```css
/* Standard Transition */
transition: all 0.3s ease-in-out;

/* Color Transition */
transition: color 0.2s ease, background-color 0.2s ease;

/* Transform Transition */
transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
```

### 6.4 Form Interactions

#### Focus States
```
- Clear outline on focused elements (focus:ring-2 focus:ring-offset-2)
- Color change to indicate active field
- Error state: Red outline + error message
```

#### Validation
- Real-time validation with inline feedback
- Clear error messages
- Success checkmark on valid fields

---

## 7. Mobile Design Considerations

### 7.1 Touch Target Sizes
- Minimum 44x44px for touch targets
- Spacing: At least 8px between targets
- Form fields: 44-48px height minimum

### 7.2 Mobile Navigation
- Hamburger menu for main navigation
- Bottom navigation bar (optional)
- Sticky header for quick access

### 7.3 Mobile Optimizations
- Single column layouts
- Larger text (16px minimum for inputs to prevent zoom)
- Full-width buttons
- Simplified forms (fewer fields per screen)
- Thumb-accessible controls (right side of screen)

---

## 8. Accessibility Design

### 8.1 Color Contrast
- WCAG AA: Minimum 4.5:1 for normal text
- WCAG AAA: Minimum 7:1 for enhanced contrast
- Use color + other indicators (not color alone)

### 8.2 Typography
- Minimum 16px for body text
- Line height: 1.5+ for readability
- Font weights: Ensure sufficient weight variation

### 8.3 Interactive Elements
- Clear focus indicators
- Keyboard navigation support
- Descriptive link text (not "Click here")
- Form labels associated with inputs

### 8.4 Images & Icons
- Always include alt text
- Icon-only buttons have title/aria-label
- Decorative images marked as such

### 8.5 Motion & Animation
- Respect prefers-reduced-motion
- Animations should not distract
- Avoid flashing/strobing effects

---

## 9. Dark Mode (Future)

### 9.1 Color Adjustments
```
Light:          Dark:
#FFFFFF      → #0F172A (Background)
#000000      → #F9FAFB (Text)
#F5F5F5      → #1F2937 (Surfaces)
#000000      → #E5E7EB (Borders)
```

### 9.2 Component Adjustments
- Reduce shadow intensity
- Increase border contrast
- Adjust image opacity
- Light images become darker

---

## 10. Design Tokens

### 10.1 Token Structure
```css
/* Colors */
--color-primary: #000000;
--color-primary-hover: #1F2937;
--color-success: #10B981;
--color-warning: #F59E0B;
--color-danger: #EF4444;

/* Spacing */
--space-xs: 4px;
--space-sm: 8px;
--space-md: 16px;
--space-lg: 24px;

/* Typography */
--font-primary: "Inter", sans-serif;
--font-size-body: 16px;
--font-weight-regular: 400;
--font-weight-bold: 600;

/* Sizing */
--button-height: 40px;
--input-height: 40px;
--border-radius: 6px;
```

---

## 11. Animation Guidelines

### 11.1 Durations
- Quick interactions: 100-150ms
- Standard transitions: 200-300ms
- Complex animations: 400-500ms

### 11.2 Easing Functions
```
ease-linear       - Constant speed
ease-in          - Slow start, fast end
ease-out         - Fast start, slow end
ease-in-out      - Slow start and end
cubic-bezier()   - Custom easing
```

### 11.3 Common Animations
- Fade in/out: 200ms ease-in-out
- Slide: 300ms ease-out
- Scale: 200ms ease-out
- Bounce: 400ms cubic-bezier(0.68, -0.55, 0.265, 1.55)

---

## 12. Component States

### 12.1 Button States
```
Default  → Hover → Active → Disabled → Loading
```

### 12.2 Form Field States
```
Default → Focused → Filled → Error → Success → Disabled
```

### 12.3 Order Status Flow
```
Pending → Processing → Shipped → Delivered → Completed
  ↓                                              
  Cancelled (anytime before shipped)
```

---

## 13. Typography Examples

### 11.1 Hero Section
```
Heading:    H1, 48px, 700 weight, Line height 1.2
Subheading: Body Large, 18px, 400 weight
```

### 11.2 Card Component
```
Title:      H4, 20px, 600 weight
Description: Body Regular, 16px, 400 weight
Label:      Label, 12px, 600 weight
```

### 11.3 Form Component
```
Label:      Label, 12px, 600 weight
Input:      Body Regular, 16px, 400 weight
Error:      Caption, 12px, 400 weight, Red
```

---

## Document Version
- **Version**: 1.0.0
- **Last Updated**: 2026-07-17
- **Author**: Design Team
- **Status**: Active
