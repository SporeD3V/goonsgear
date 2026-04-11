# GoonsGear Architecture & UX Analysis

> **Purpose:** Document the current architecture, identify friction points, and propose improvements to minimize steps for both customers and admin.
> **Date:** April 11, 2026

---

## Table of Contents

1. [System Architecture Overview](#1-system-architecture-overview)
2. [Data Model Map](#2-data-model-map)
3. [Storefront User Flows](#3-storefront-user-flows)
4. [Admin Dashboard Flows](#4-admin-dashboard-flows)
5. [Friction Analysis — Storefront](#5-friction-analysis--storefront)
6. [Friction Analysis — Admin](#6-friction-analysis--admin)
7. [Proposed Improvements — Storefront](#7-proposed-improvements--storefront)
8. [Proposed Improvements — Admin](#8-proposed-improvements--admin)
9. [Implementation Priority](#9-implementation-priority)

---

## 1. System Architecture Overview

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.3 |
| Frontend Interactivity | Livewire 4 (Islands), Alpine.js |
| Styling | Tailwind CSS via Vite |
| Payments | PayPal (SDK integration) |
| Email | Brevo API |
| Shipping | DHL (tracking links) |
| Security | reCAPTCHA v3, HMAC-SHA256 order signing |
| Database | MySQL |

### Architecture Pattern
- **Storefront:** Server-rendered Blade templates + Livewire 4 Islands (SFC pattern) for reactive sections
- **Admin:** Traditional Blade layout (`@extends`) + Livewire components for CRUD managers
- **Cart:** Session-based with DB sync for authenticated users
- **Checkout:** Two-phase (session pending → confirmed) with PayPal integration

### Page Inventory

| Area | Pages | Tech |
|------|-------|------|
| **Storefront** | Homepage, Catalog, Product Detail, Cart, Checkout, Success, Account | Blade + Livewire Islands |
| **Auth** | Login, Register, Forgot Password, Reset Password | Blade forms |
| **Admin** | 13 pages (Products, Orders, Categories, Tags, 3× Discounts, 3× Settings, 2× Utility, Activity Log) | Blade layout + Livewire managers |

---

## 2. Data Model Map

### Domain Groups

```
┌─────────────── CATALOG ───────────────┐
│  Product ─┬─ ProductVariant            │
│           ├─ ProductMedia              │
│           ├─ Category (many-to-many)   │
│           └─ Tag (many-to-many)        │
│  Category ── parent/children (tree)    │
│  Tag ── type: artist | brand | custom  │
└────────────────────────────────────────┘

┌─────────────── PRICING ───────────────┐
│  Coupon ── scoped (all/product/cat/tag)│
│         ── personal (user assignments) │
│         ── stackable (group combos)    │
│  BundleDiscount ── BundleDiscountItem  │
│  RegionalDiscount ── by country code   │
└────────────────────────────────────────┘

┌─────────────── ORDERS ────────────────┐
│  Order ── OrderItem                    │
│        ── OrderCouponUsage             │
└────────────────────────────────────────┘

┌─────────────── USER ──────────────────┐
│  User ── UserCartItem (DB cart)        │
│       ── SizeProfile                   │
│       ── TagFollow (notifications)     │
│       ── StockAlertSubscription        │
│       ── Coupons (personal, via pivot) │
└────────────────────────────────────────┘

┌─────────────── MARKETING ─────────────┐
│  NewsletterSubscriber                  │
│  CartAbandonment (recovery)            │
│  TagNotificationDispatch               │
└────────────────────────────────────────┘

┌─────────────── SYSTEM ────────────────┐
│  AbandonedCartSetting (singleton)      │
│  IntegrationSetting (encrypted KV)     │
│  UrlRedirect                           │
│  AdminActivityLog                      │
│  EditHistory                           │
└────────────────────────────────────────┘
```

---

## 3. Storefront User Flows

### Flow A: Browse → Purchase (Happy Path)

```
Homepage/Catalog ──→ Product Detail ──→ Add to Cart ──→ Cart ──→ Checkout ──→ Success
     (1)                 (2)              (3)           (4)        (5)         (6)
```

**Current step count: 6 pages, 3 form interactions**

| Step | Page | User Actions | Forms/Clicks |
|------|------|-------------|-------------|
| 1 | Homepage OR Catalog | Browse, filter, search | 0-2 (filter/search) |
| 2 | Product Detail | Select variant, view gallery | 1-2 clicks |
| 3 | Add to Cart | Click "Add to Cart" | 1 click (stays on page or goes to cart) |
| 4 | Cart | Review items, apply coupons, adjust qty | 0-3 interactions |
| 5 | Checkout | Fill 13+ address fields, choose payment | 1 large form |
| 6 | Success | View confirmation, optional size profile | Read-only |

### Flow B: Returning Customer

```
Homepage ──→ Product Detail ──→ Add to Cart ──→ Checkout ──→ Success
                                                (pre-filled)
```

**Step count: 5 pages, 1-2 form interactions** (address auto-filled from account)

### Flow C: Account Management

```
Account Dashboard (single page):
  ├─ Profile (read-only)
  ├─ Email Notifications (toggle form)
  ├─ Delivery Address (form)
  ├─ Size Profiles (inline CRUD)
  ├─ Tag Follows (manage subscriptions)
  └─ Recent Orders (read-only list)
```

**Good: All account features on one page. No navigation needed.**

### Flow D: Abandoned Cart Recovery

```
Email link ──→ Cart restored ──→ Checkout ──→ Success
```

**Step count: 3 pages, 1 form**

---

## 4. Admin Dashboard Flows

### Current Navigation Structure

```
SIDEBAR
├─ Orders              (list → detail)
├─ Products            (list → edit page, inline edit on list)
├─ Categories          (list + modal CRUD)
├─ Tags ▼              (collapsible)
│   ├─ Artists
│   ├─ Brands
│   └─ Custom Tags
├─ ─── divider ───
├─ Discounts ▼         (collapsible)
│   ├─ Coupons
│   ├─ Bundle Discounts
│   └─ Regional Discounts
├─ Settings ▼          (collapsible)
│   ├─ Cart Reminders
│   ├─ Integrations
│   └─ URL Redirects
└─ Utility ▼           (collapsible)
    ├─ Clear Caches    (action button)
    ├─ Clear Logs      (action button)
    ├─ Fallback Media
    └─ Sync Log
```

### Admin Flow: Add a New Product

```
Products list ──→ Create Product page ──→ Save ──→ Edit page ──→ Add Variant ──→ Upload Media ──→ Save
    (1)                  (2)               (3)        (4)            (5)             (6)          (7)
```

**Problem: 7 steps across 3-4 page loads to get a product fully set up.**

### Admin Flow: Manage an Order

```
Orders list ──→ Order Detail ──→ Update status/tracking ──→ Save
    (1)              (2)                (3)                  (4)
```

**Reasonable: 2-page flow.**

### Admin Flow: Create a Coupon

```
Coupons list ──→ Click "New" ──→ Fill modal (15+ fields) ──→ Save
    (1)              (2)                 (3)                   (4)
```

**Problem: Too many fields in one modal for complex coupons (personal + scoped + stackable).**

---

## 5. Friction Analysis — Storefront

### 5.1 Checkout Form: Too Many Fields

**Problem:** The checkout form has **13+ visible fields** in a single column, plus optional apartment details. For a first-time customer this is overwhelming.

**Current fields:**
- Email
- First name, Last name
- Phone (optional), Country
- State (optional), City
- Postal code
- Street name, Street number
- Apartment block (optional), Entrance (optional)
- Floor (optional), Apartment number (optional)

**Impact:** High cart abandonment risk. 13 fields creates visual friction even though 4 are optional.

### 5.2 No Guest Checkout Clarity

**Problem:** There's no clear messaging that checkout works without an account. Users may bounce to register because they don't realize they can just proceed.

### 5.3 Cart Coupon UI is Complex

**Problem:** The cart page has:
- Manual coupon code input
- "Available coupons" multi-select section for logged-in users
- Applied coupons list with individual remove buttons
- Invalid coupon messages
- Recommendation messages

This is **5 separate visual sections** just for coupons. The cognitive load is high.

### 5.4 Product Detail → Cart: No Quick Buy

**Problem:** The flow is always Product → Add to Cart → Cart page → Checkout. There's no "Buy Now" shortcut to skip the cart for single-item purchases.

### 5.5 No Cart Drawer / Slide-Out

**Problem:** Adding an item to cart redirects to the cart page or shows a flash message. No non-disruptive confirmation. Users lose their browsing context.

### 5.6 Mobile: Cart Table is Poorly Optimized

**Problem:** The cart uses a `<table>` layout with columns (Item, Price, Qty, Total, Actions). On mobile with `overflow-x-auto`, users must scroll horizontally — a known UX anti-pattern for mobile shopping.

---

## 6. Friction Analysis — Admin

### 6.1 Product Creation Requires Multiple Page Loads

**Current:** Create product (basic info) → Save → Navigate to edit → Add variants → Upload media → Manage SEO. Each step is a page reload.

**Problem:** The admin must:
1. Fill out and save the product form
2. Wait for redirect to the edit page  
3. Scroll to add variants
4. Scroll to upload media
5. Every change requires a full form POST + page reload

### 6.2 Admin Layout: Single White Box

**Current:** The entire admin page content is wrapped in a single `<div class="rounded-lg bg-white p-6 shadow-sm">`. Every admin page is one big white card.

**Problem:** There's no visual separation between:
- Filters vs. content
- Summary info vs. action areas
- Status indicators vs. data tables
- Toolbar vs. form areas

This makes it hard to scan information quickly on complex pages like Product Edit or Order Detail.

### 6.3 No Dashboard/Overview Page

**Problem:** When an admin logs in and goes to `/admin`, there's no landing page. They must navigate to a specific section. There's no:
- Quick stats (orders today, revenue, low stock alerts)
- Recent activity feed
- Pending action items
- Quick navigation cards

### 6.4 Inconsistent CRUD Patterns

| Page | CRUD Pattern | Creates via |
|------|-------------|------------|
| Products | Separate create/edit pages + inline edit on list | New page |
| Categories | Modal CRUD | Modal |
| Tags | Modal CRUD | Modal |
| Coupons | Modal CRUD | Modal |
| Bundle Discounts | Modal CRUD | Modal |
| Regional Discounts | Modal CRUD | Modal |
| URL Redirects | Modal CRUD | Modal |
| Orders | Read + inline edit on detail | N/A (from storefront) |

**Products is the outlier.** All other entities use the modal pattern, but products use separate pages. This makes sense for complexity, but the edit page is a single long scrollable form with no sections.

### 6.5 Settings Scattered Across Multiple Sub-Sections

**Current:** Settings are split into:
- **Settings:** Cart Reminders, Integrations, URL Redirects
- **Utility:** Clear Caches, Clear Logs, Fallback Media, Sync Log

**Problem:** "URL Redirects" is a Settings item but logically relates to SEO. "Fallback Media" is under Utility but relates to Products. The grouping is organizational but not task-based.

### 6.6 No Bulk Actions

**Problem:** There's no way to bulk-update products (e.g., set 20 products to "active"), bulk-delete, or bulk-export. Every action is one-at-a-time.

---

## 7. Proposed Improvements — Storefront

### 7.1 Streamline Checkout Form

**Goal:** Reduce visual field count from 13+ → progressive disclosure.

**Approach:** 
- Group fields into collapsible steps or sections:
  1. **Contact** (email, phone)
  2. **Shipping** (country → auto-show state/city/postal) 
  3. **Address** (street name + number, optional apartment details collapsed by default)
- Show optional apartment fields behind a "Add apartment details" toggle (reduces visible fields by 4)
- Auto-detect country from browser locale/IP for pre-fill

**Expected impact:** Visual field count drops from 13+ to ~7 initially visible.

### 7.2 Cart Drawer Instead of Cart Page

**Goal:** Let users add items without leaving the current page.

**Approach:**
- Add a slide-out cart drawer (Livewire component) triggered by "Add to Cart"
- Cart drawer shows: items, subtotal, "View Cart" link, "Checkout" button
- Keep the full cart page for detailed coupon management and quantity editing

**Expected impact:** Reduces happy path from 6 → 5 steps. Users stay in browsing context.

### 7.3 Simplify Cart Coupon UI

**Goal:** Reduce 5 coupon sections → 2.

**Approach:**
- Single input with "Apply" button
- Below it: combined list of applied + available coupons (toggle-style, not separate sections)
- Auto-apply best combination when user selects from available coupons

### 7.4 Add "Buy Now" Button

**Goal:** Skip cart for single-item purchases.

**Approach:**
- On Product Detail, add a "Buy Now" button alongside "Add to Cart"
- "Buy Now" adds item to cart + redirects straight to checkout
- One less page in the flow for impulse purchases

### 7.5 Mobile Cart: Card Layout

**Goal:** Replace horizontal-scrolling table with mobile-friendly card layout.

**Approach:**
- On mobile breakpoints, render each cart item as a card (image, name, price, qty +/- buttons, remove)
- No `<table>` on mobile at all

---

## 8. Proposed Improvements — Admin

### 8.1 Reorganize Admin into Logical Containers

**Goal:** Each admin page uses separated visual containers based on logical grouping instead of one big white box.

**Proposed container structure per page:**

#### Product Edit Page
```
┌─── Container 1: Product Identity ────────┐
│ Name, Slug, Status, Primary Category      │
│ Published At                              │
└───────────────────────────────────────────┘
┌─── Container 2: Content ─────────────────┐
│ Excerpt, Description                      │
└───────────────────────────────────────────┘
┌─── Container 3: Categorization ──────────┐
│ Additional Categories, Artists/Brands     │
│ Featured, Preorder, Bundle Exclusive flags│
└───────────────────────────────────────────┘
┌─── Container 4: Preorder Settings ───────┐
│ (Conditionally shown if Preorder enabled) │
│ Preorder Available From, Expected Ship At │
└───────────────────────────────────────────┘
┌─── Container 5: SEO ────────────────────┐
│ Meta Title (with character counter)       │
│ Meta Description (with character counter) │
└───────────────────────────────────────────┘
┌─── Container 6: Media Management ────────┐
│ Upload area, existing media gallery       │
│ Primary image selector, variant assignment│
└───────────────────────────────────────────┘
┌─── Container 7: Variants ────────────────┐
│ Variant list + inline CRUD               │
│ (No separate page for creating variants)  │
└───────────────────────────────────────────┘
```

#### Order Detail Page
```
┌─── Container 1: Order Header ────────────┐
│ Order #, status badge, date, quick stats │
└───────────────────────────────────────────┘
┌─── Grid: Info Cards ─────────────────────┐
│ ┌── Customer ──┐  ┌── Shipping ─────────┐│
│ │ Name, email  │  │ Full address        ││
│ │ Phone        │  │                     ││
│ └──────────────┘  └─────────────────────┘│
│ ┌── Payment ───┐  ┌── Shipping/Track ───┐│
│ │ Method, IDs  │  │ Carrier, tracking # ││
│ └──────────────┘  └─────────────────────┘│
└───────────────────────────────────────────┘
┌─── Container 2: Actions ─────────────────┐
│ Status dropdown, tracking input, Save btn│
└───────────────────────────────────────────┘
┌─── Container 3: Items ───────────────────┐
│ Items table + pricing summary            │
└───────────────────────────────────────────┘
```

#### List Pages (Products, Orders, etc.)
```
┌─── Container 1: Filters & Actions ───────┐
│ Search, dropdowns, "New" button          │
└───────────────────────────────────────────┘
┌─── Container 2: Data Table ──────────────┐
│ Sortable table + pagination              │
└───────────────────────────────────────────┘
```

### 8.2 Reorganize Sidebar Navigation

**Goal:** Group by admin task workflow, not by data type.

**Proposed structure:**

```
SIDEBAR (reorganized)
┌─ DASHBOARD (new)           ← Quick stats, pending items
├─ ── STORE MANAGEMENT ──
├─ Orders                    ← Most frequent admin task
├─ Products                  ← Second most frequent
├─ Categories
├─ Tags ▼
│   ├─ Artists
│   ├─ Brands  
│   └─ Custom
├─ ── PRICING & PROMOTIONS ──
├─ Coupons
├─ Bundle Deals
├─ Regional Pricing
├─ ── CONFIGURATION ──
├─ Integrations              (PayPal, Brevo, DHL, reCAPTCHA)
├─ Cart Recovery             (Abandoned cart settings)
├─ URL Redirects
├─ ── SYSTEM ──
├─ Activity Log
├─ Media Maintenance
├─ Clear Caches  ⚡          (inline action, not a page)
└─ Clear Logs    ⚡          (inline action, not a page)
```

**Key changes:**
- Added **section labels** (Store Management, Pricing, Configuration, System) for scannability
- Removed nested collapsible groups — replaced with flat list with section dividers (fewer clicks)
- "Discounts" renamed to "Pricing & Promotions" (clearer)
- "Bundle Discounts" → "Bundle Deals" (user-friendly name)
- "Cart Reminders" → "Cart Recovery" (describes the feature, not the mechanism)
- Cache/Log clearing remain as action buttons but moved to bottom

### 8.3 Add Admin Dashboard Landing Page

**Goal:** When admin goes to `/admin`, show an overview page.

**Proposed sections:**

```
┌─── Quick Stats (4 cards) ────────────────┐
│ [Orders Today] [Revenue This Week]       │
│ [Low Stock Items] [Pending Orders]       │
└───────────────────────────────────────────┘
┌─── Recent Orders ────────────────────────┐
│ Last 5 orders (mini-table)               │
└───────────────────────────────────────────┘
┌─── Attention Required ───────────────────┐
│ • 3 products with zero stock             │
│ • 2 orders pending shipment              │
│ • 12 stock alert subscribers waiting     │
└───────────────────────────────────────────┘
```

### 8.4 Product Edit: Section-Based Layout

**Goal:** Replace the single long form with visually separated containers (see 8.1).

**Design principles for admin containers:**
- Each container: `rounded-lg border border-slate-200 bg-white p-6 shadow-sm`
- Container headers: `text-sm font-semibold text-slate-800 uppercase tracking-wide`
- Spacing between containers: `space-y-6`
- Related fields grouped within a container, unrelated fields in separate containers
- Conditional sections hidden by default (e.g., preorder details only when preorder is checked)

### 8.5 Design Consistency Rules for Admin

**Container styling:**
```css
/* Base container */
.admin-container: rounded-lg border border-slate-200 bg-white shadow-sm

/* Container with padding */
.admin-container-padded: p-5 or p-6

/* Container header */
.admin-container-header: text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4

/* Compact info card */
.admin-info-card: rounded-lg border border-slate-200 bg-slate-50 p-4
```

**Color system (admin):**
| Purpose | Color | Tailwind |
|---------|-------|----------|
| Sidebar background | Dark slate | `bg-slate-900` |
| Page background | Light gray | `bg-slate-100` |
| Container background | White | `bg-white` |
| Info card background | Very light gray | `bg-slate-50` |
| Primary action | Blue | `bg-blue-600 hover:bg-blue-700` |
| Danger action | Red | `bg-red-600 hover:bg-red-700` |
| Success indicator | Green | `text-emerald-600 bg-emerald-50` |
| Warning indicator | Amber | `text-amber-600 bg-amber-50` |
| Neutral text | Slate grades | `text-slate-500` to `text-slate-900` |
| Borders | Subtle slate | `border-slate-200` |

**Typography hierarchy (admin):**
| Level | Usage | Tailwind |
|-------|-------|----------|
| Page title | Top bar title | `text-lg font-semibold text-slate-900` |
| Section header | Container title | `text-sm font-semibold text-slate-700 uppercase tracking-wide` |
| Field label | Form labels | `text-sm font-medium text-slate-700` |
| Body text | Descriptions, hints | `text-sm text-slate-500` |
| Badge | Status indicators | `text-xs font-medium px-2 py-0.5 rounded-full` |

**Status badge colors:**
| Status | Colors |
|--------|--------|
| Active / Paid / Completed | `bg-emerald-100 text-emerald-700` |
| Draft / Pending | `bg-amber-100 text-amber-700` |
| Inactive / Archived / Cancelled | `bg-slate-100 text-slate-600` |
| Error / Failed / Refunded | `bg-red-100 text-red-700` |
| Processing / Shipped | `bg-blue-100 text-blue-700` |

---

## 9. Implementation Priority

### Phase 1: Admin Container Separation (High Impact, Low Risk) ✅ DONE
1. ~~**Product edit page** — Break single form into sectioned containers~~
2. ~~**Order detail page** — Separate info cards, actions, and items into containers~~
3. ~~**All list pages** — Separate filters and data table into distinct containers~~
4. ~~**Sidebar reorganization** — Collapsible sections with route-aware auto-expand~~

### Phase 2: Admin Dashboard (Full) ✅ DONE
4. ~~**Create admin dashboard** — 5-tab analytics dashboard (Overview, Sales, Inventory, Promotions, Customers) with Chart.js, DashboardStatsService, and cached queries~~

### Phase 3: Storefront Quick Wins
5. **Checkout form** — Progressive disclosure, collapse optional apartment fields
6. **Mobile cart** — Card layout instead of table

### Phase 4: Storefront Enhancements
7. **Cart drawer** — Slide-out mini-cart on "Add to Cart"
8. **Buy Now button** — Skip cart for single-item purchases
9. **Coupon UI simplification** — Combined applied + available section

---

## Design Principles Applied

1. **Progressive Disclosure** — Show only what's needed now, reveal complexity on demand
2. **Proximity** — Group related controls together in containers
3. **Visual Hierarchy** — Use container boundaries to establish scanning order
4. **Consistency** — Same patterns across all admin pages (container styling, badge colors, typography)

---

## 10. Dashboard Analytics — Stats & Graphs Roadmap

> What data already exists in the database that can power meaningful business intelligence? This section maps every useful metric, the tables/columns it queries, the best visualization type, and how to group the dashboard for maximum insight at a glance.

### 10.1 Available Data Sources

| Source Table | Key Columns for Analytics | Records Meaning |
|---|---|---|
| `orders` | `status`, `payment_status`, `total`, `subtotal`, `discount_total`, `regional_discount_total`, `bundle_discount_total`, `currency`, `country`, `placed_at`, `shipped_at`, `coupon_code`, `bundle_sku` | Every completed/pending/cancelled order |
| `order_items` | `product_id`, `product_variant_id`, `sku`, `unit_price`, `quantity`, `line_total` | Individual line items per order |
| `order_coupon_usages` | `coupon_id`, `coupon_code`, `discount_total`, `applied_position` | Which coupons were used, how much they discounted |
| `products` | `status`, `is_featured`, `is_preorder`, `is_bundle_exclusive`, `published_at`, `created_at` | Catalog lifecycle |
| `product_variants` | `price`, `compare_at_price`, `stock_quantity`, `is_active`, `variant_type` | Inventory and pricing state |
| `categories` | `name`, `is_active` | Product taxonomy |
| `tags` | `name`, `type` (artist/brand/custom), `is_active` | Brand and artist associations |
| `coupons` | `code`, `type`, `value`, `used_count`, `usage_limit`, `is_active`, `starts_at`, `ends_at` | Promotion performance |
| `bundle_discounts` | `name`, `bundle_price`, `discount_value`, `is_active` | Bundle deal configuration |
| `regional_discounts` | `country_code`, `discount_type`, `discount_value`, `is_active` | Geo-pricing rules |
| `users` | `is_admin`, `delivery_country`, `created_at` | Customer base |
| `cart_abandonments` | `abandoned_at`, `reminder_sent_at`, `recovered_at` | Recovery funnel |
| `stock_alert_subscriptions` | `product_variant_id`, `is_active`, `notified_at` | Demand signal |
| `tag_follows` | `user_id`, `tag_id`, `notify_new_drops`, `notify_discounts` | Brand/artist interest signal |
| `tag_notification_dispatches` | `notification_type`, `dispatched_at` | Marketing email volume |
| `newsletter_subscribers` | `subscribed_at`, `unsubscribed_at` | Email list health |
| `user_cart_items` | `product_variant_id`, `quantity` | Live cart contents |
| `sessions` | `user_id`, `last_activity` | Active visitors |

---

### 10.2 Revenue & Sales Graphs

#### A. Revenue Over Time (Line Chart)
- **Query:** `orders` grouped by `placed_at` (day/week/month), `SUM(total)` where `payment_status = 'paid'`
- **Visualization:** Line chart with selectable period (7d, 30d, 90d, 12mo, all-time)
- **Variants:** Overlay lines for gross revenue (`SUM(subtotal)`) vs. net revenue (`SUM(total)`) vs. discounts given (`SUM(discount_total + regional_discount_total + bundle_discount_total)`)
- **Business value:** Spot sales trends, seasonal patterns, impact of promotions

#### B. Average Order Value (KPI + Sparkline)
- **Query:** `AVG(total)` from `orders` where `payment_status = 'paid'`, grouped by period
- **Visualization:** Big number with sparkline trend underneath
- **Compare:** Current period vs. previous period (percentage change arrow)

#### C. Orders by Status (Donut/Pie Chart)
- **Query:** `COUNT(*)` from `orders` grouped by `status`
- **Visualization:** Donut chart with: pending (amber), processing (blue), shipped (indigo), delivered (green), cancelled (red), refunded (slate)
- **Business value:** Quick view of order pipeline health

#### D. Revenue by Country (Horizontal Bar Chart)
- **Query:** `orders` grouped by `country`, `SUM(total)` where `payment_status = 'paid'`
- **Visualization:** Horizontal bar chart, top 10 countries, "Other" bucket
- **Business value:** Identify top markets, validate regional discount strategy

#### E. Revenue by Payment Status (Stacked Bar)
- **Query:** `orders` grouped by `payment_status` per period
- **Visualization:** Stacked bar showing paid vs. pending vs. failed over time
- **Business value:** Track payment failure rates

---

### 10.3 Product & Inventory Graphs

#### F. Top Selling Products (Bar Chart)
- **Query:** `order_items` → `SUM(quantity)` grouped by `product_id`, joined with `products.name`
- **Visualization:** Horizontal bar chart, top 15 products by units sold
- **Period filter:** 7d, 30d, 90d, all-time
- **Alternative:** Top by revenue (`SUM(line_total)`) instead of units

#### G. Top Selling Variants (Table)
- **Query:** `order_items` → `SUM(quantity)`, `SUM(line_total)` grouped by `product_variant_id`, joined with variant name + SKU
- **Visualization:** Sortable table (SKU, Product, Variant, Units, Revenue)
- **Business value:** Know which sizes/colors drive the most revenue

#### H. Inventory Health (Grouped Bar / Heatmap)
- **Query:** `product_variants` categorized into stock buckets: 0 (out of stock), 1–5 (critical), 6–20 (low), 21–100 (healthy), 100+ (overstocked)
- **Visualization:** Stacked horizontal bar or color-coded table
- **Business value:** Restock planning at a glance

#### I. Stock Alert Demand (Table + Count)
- **Query:** `stock_alert_subscriptions` where `is_active = 1` grouped by `product_variant_id`, `COUNT(*) as waiting_customers`
- **Visualization:** Table sorted by waiting count, descending
- **Business value:** Prioritize restocking by actual customer demand — the variant with the most subscribers should be restocked first

#### J. Product Status Breakdown (Donut Chart)
- **Query:** `products` grouped by `status` (active, draft, archived)
- **Visualization:** Donut chart
- **Business value:** Catalog hygiene — how many drafts sitting unpublished?

#### K. Products Without Media (Alert List)
- **Query:** `products` LEFT JOIN `product_media` where media is NULL and `status = 'active'`
- **Visualization:** Warning list / badge count
- **Business value:** Active products missing images = lost sales

---

### 10.4 Customer & Engagement Graphs

#### L. New Customers Over Time (Area Chart)
- **Query:** `users` where `is_admin = 0`, grouped by `created_at` (week/month)
- **Visualization:** Area chart showing registrations over time
- **Business value:** Track growth, correlate spikes with marketing campaigns

#### M. Customer Geography (Choropleth Map or Bar)
- **Query:** `users` grouped by `delivery_country`, OR `orders` grouped by `country`
- **Visualization:** World map heatmap or horizontal bar (top 10 countries)
- **Business value:** Market distribution, shipping cost planning

#### N. Repeat Customer Rate (KPI + Breakdown)
- **Query:** `orders` grouped by `email`, count customers with 1 order vs. 2+ orders
- **Visualization:** Big number (% repeat), with breakdown (1 order, 2, 3, 4+)
- **Business value:** Retention health — are customers coming back?

#### O. Active Carts Right Now (Live KPI)
- **Query:** `user_cart_items` joined with `sessions` where `last_activity` > now - 30min, `COUNT(DISTINCT user_id)`
- **Visualization:** Live number badge
- **Business value:** Real-time shopping activity

#### P. Tag Follow Popularity (Bar Chart)
- **Query:** `tag_follows` grouped by `tag_id`, joined with `tags.name`, `COUNT(*)`
- **Visualization:** Bar chart split by tag type (artist vs. brand)
- **Business value:** Which artists/brands have the most engaged followers — drives merch acquisition decisions

#### Q. Newsletter Health (KPI Cards)
- **Query:** `newsletter_subscribers` — total, active (`unsubscribed_at IS NULL`), unsubscribed, new this month
- **Visualization:** 4 small KPI cards
- **Business value:** Email marketing reach

---

### 10.5 Discount & Promotion Analytics

#### R. Coupon Usage Leaderboard (Table)
- **Query:** `order_coupon_usages` grouped by `coupon_code`, `COUNT(*)` as times_used, `SUM(discount_total)` as total_discounted
- **Visualization:** Sortable table (Code, Times Used, Revenue Discounted, Avg Discount)
- **Business value:** Which coupons drive sales vs. which are just margin erosion

#### S. Discount Margin Impact (KPI + Trend)
- **Query:** `orders` → `SUM(discount_total + regional_discount_total + bundle_discount_total) / SUM(subtotal) * 100` = discount percentage
- **Visualization:** Big percentage with trend line
- **Business value:** Track if discounts are eating into margins over time

#### T. Bundle Discount Performance (Table)
- **Query:** `orders` where `bundle_sku IS NOT NULL`, `COUNT(*)`, `SUM(bundle_discount_total)`, joined with `bundle_discounts.name`
- **Visualization:** Table (Bundle Name, Orders Using It, Total Bundle Discount, Avg Order Value)
- **Business value:** Are bundles increasing order value or just discounting existing purchases?

#### U. Regional Discount Usage (Bar Chart)
- **Query:** `orders` where `regional_discount_total > 0`, grouped by `country`, `SUM(regional_discount_total)`
- **Visualization:** Bar chart by country
- **Business value:** Which markets depend on regional pricing to convert

---

### 10.6 Cart Recovery & Abandonment

#### V. Abandonment Funnel (Funnel Chart)
- **Query:** `cart_abandonments` grouped into stages: `COUNT(*)` total, where `reminder_sent_at IS NOT NULL`, where `recovered_at IS NOT NULL`
- **Visualization:** Funnel: Abandoned → Reminded → Recovered
- **Math:** Recovery rate = recovered / reminded × 100
- **Business value:** Is the abandoned cart email system working? What's the ROI?

#### W. Recovery Revenue (KPI)
- **Query:** Join `cart_abandonments` (where `recovered_at IS NOT NULL`) token → match against `orders` placed within 24h of recovery
- **Visualization:** Total recovered revenue, count of recovered orders
- **Business value:** Direct dollar value of the cart recovery system

#### X. Abandonment Timeline (Line Chart)
- **Query:** `cart_abandonments` grouped by `abandoned_at` (day/week)
- **Visualization:** Line chart of abandonment volume over time
- **Business value:** Spot if abandonment rate is growing (possible UX problem)

---

### 10.7 Proposed Dashboard Layout

The expanded dashboard could be organized into **tabs or scrollable sections** to avoid overwhelming the admin:

```
┌─────────────────────────────────────────────────────────────────┐
│  OVERVIEW TAB (default view when admin opens /admin)            │
│                                                                 │
│  ┌─ KPI Row (4 cards) ─────────────────────────────────────────┐│
│  │ Total Revenue ▲12%  │  Orders Today  │  AOV  │  Pending     ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  ┌─ Attention Required ────────────────────────────────────────┐│
│  │ • 5 variants out of stock   • 3 orders pending processing  ││
│  │ • 28 customers on stock alert wait lists                    ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  ┌─ Revenue (30d) ───────┐  ┌─ Orders by Status ──────────────┐│
│  │ [Line Chart]          │  │ [Donut Chart]                    ││
│  └───────────────────────┘  └──────────────────────────────────┘│
│                                                                 │
│  ┌─ Recent Orders (table — last 10) ──────────────────────────┐│
│  │ #GG-001  John D.  Pending  €89.00   Apr 11                 ││
│  │ ...                                                         ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  SALES TAB                                                      │
│                                                                 │
│  ┌─ Revenue Over Time ────────────────────────────────────────┐│
│  │ [Line Chart — Gross / Net / Discounts] [Period: 7d 30d 90d]││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  ┌─ Revenue by Country ──────┐  ┌─ Top Products ─────────────┐│
│  │ [Horiz Bar — top 10]      │  │ [Horiz Bar — top 15]       ││
│  └───────────────────────────┘  └─────────────────────────────┘│
│                                                                 │
│  ┌─ Repeat Customer Rate ────┐  ┌─ Avg Order Value Trend ────┐│
│  │ 34% ▲2%  [1x: 66%]       │  │ [Sparkline]                ││
│  │            [2x: 22%]      │  │ €74.50 → €81.20            ││
│  │            [3+: 12%]      │  │                             ││
│  └───────────────────────────┘  └─────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  INVENTORY TAB                                                  │
│                                                                 │
│  ┌─ Stock Health ────────────────────────────────────────────┐ │
│  │ [Grouped bar: Out of Stock | Critical | Low | OK | Over]  │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Stock Alert Demand ──────┐  ┌─ Products Missing Media ───┐│
│  │ [Table: Variant, Waiting] │  │ [Warning list]              ││
│  └───────────────────────────┘  └─────────────────────────────┘│
│                                                                 │
│  ┌─ Product Status Breakdown ┐  ┌─ Top Variants by Units ────┐│
│  │ [Donut: active/draft/arch]│  │ [Table: SKU, Units, Rev]   ││
│  └───────────────────────────┘  └─────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  PROMOTIONS TAB                                                 │
│                                                                 │
│  ┌─ Discount Margin Impact ──────────────────────────────────┐ │
│  │ 8.3% of gross revenue   [Trend line over 90d]             │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Coupon Leaderboard ──────┐  ┌─ Bundle Performance ───────┐│
│  │ [Table: code, uses, $]    │  │ [Table: name, orders, $]   ││
│  └───────────────────────────┘  └─────────────────────────────┘│
│                                                                 │
│  ┌─ Regional Discount by Country ─┐  ┌─ Cart Recovery ───────┐│
│  │ [Bar chart]                    │  │ Abandoned: 142         ││
│  │                                │  │ Reminded:   89         ││
│  │                                │  │ Recovered:  31 (35%)   ││
│  │                                │  │ Rev: €2,840            ││
│  └────────────────────────────────┘  └────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  CUSTOMERS TAB                                                  │
│                                                                 │
│  ┌─ KPI Row ───────────────────────────────────────────────────┐│
│  │ Total Customers │ New This Month │ Newsletter │ Active Carts ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  ┌─ Registrations Over Time ─┐  ┌─ Geography ────────────────┐│
│  │ [Area chart by week]      │  │ [Bar: top 10 countries]    ││
│  └───────────────────────────┘  └─────────────────────────────┘│
│                                                                 │
│  ┌─ Tag Follow Popularity ───────────────────────────────────┐ │
│  │ [Bar chart split: artists (blue) vs brands (green)]       │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Newsletter Health ───────────────────────────────────────┐ │
│  │ [Area chart: subscribers over time with unsub overlay]    │ │
│  └───────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

### 10.8 Implementation Approach

#### Charting Library
For rendering charts in a Laravel/Livewire app, recommended options:
- **Chart.js** via Alpine.js — lightweight, no build step required, render `<canvas>` directly
- **ApexCharts** — richer interactivity (zoom, tooltips), also works well with Alpine.js
- Avoid heavy JS frameworks (React/D3) — overkill for admin dashboards

#### Querying Strategy
- Use **Eloquent** with `selectRaw()` / `groupBy()` for aggregations
- Cache expensive queries (`Cache::remember()` with 5–15 min TTL for dashboard stats)
- Use **database indexes** on `orders.placed_at`, `orders.status`, `orders.payment_status`, `orders.country`, `order_items.product_id`
- Consider a `DashboardStatsService` class to encapsulate all query logic

#### Progressive Build Order
1. ~~**Phase A:** Expand current dashboard with KPI cards + recent orders~~ ✅ DONE
2. ~~**Phase B:** Add revenue line chart + orders donut (Chart.js via CDN, Overview tab)~~ ✅ DONE
3. ~~**Phase C:** Add inventory health section (stock buckets, alert demand)~~ ✅ DONE
4. ~~**Phase D:** Add promotions tab (coupon leaderboard, discount impact)~~ ✅ DONE
5. ~~**Phase E:** Add customer analytics tab (registrations, geography, tag follows)~~ ✅ DONE
6. ~~**Phase F:** Add cart recovery funnel + metrics~~ ✅ DONE

---

### 10.9 SQL Query Sketches

Quick reference for the key queries (adapt to Eloquent):

```sql
-- A. Revenue over time (daily, last 30 days)
SELECT DATE(placed_at) AS day,
       SUM(total) AS revenue,
       SUM(subtotal) AS gross,
       SUM(discount_total + regional_discount_total + bundle_discount_total) AS discounts,
       COUNT(*) AS order_count
FROM orders
WHERE payment_status = 'paid'
  AND placed_at >= NOW() - INTERVAL 30 DAY
GROUP BY DATE(placed_at)
ORDER BY day;

-- B. Average Order Value trend
SELECT DATE_FORMAT(placed_at, '%Y-%m') AS month,
       AVG(total) AS aov,
       COUNT(*) AS orders
FROM orders
WHERE payment_status = 'paid'
GROUP BY month
ORDER BY month;

-- F. Top selling products (last 30 days)
SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
JOIN products p ON p.id = oi.product_id
WHERE o.payment_status = 'paid'
  AND o.placed_at >= NOW() - INTERVAL 30 DAY
GROUP BY oi.product_id, p.name
ORDER BY units DESC
LIMIT 15;

-- H. Inventory health buckets
SELECT
  SUM(stock_quantity = 0) AS out_of_stock,
  SUM(stock_quantity BETWEEN 1 AND 5) AS critical,
  SUM(stock_quantity BETWEEN 6 AND 20) AS low,
  SUM(stock_quantity BETWEEN 21 AND 100) AS healthy,
  SUM(stock_quantity > 100) AS overstocked
FROM product_variants
WHERE is_active = 1;

-- I. Stock alert demand
SELECT pv.sku, p.name AS product, pv.name AS variant,
       COUNT(*) AS waiting
FROM stock_alert_subscriptions sas
JOIN product_variants pv ON pv.id = sas.product_variant_id
JOIN products p ON p.id = pv.product_id
WHERE sas.is_active = 1 AND sas.notified_at IS NULL
GROUP BY sas.product_variant_id, pv.sku, p.name, pv.name
ORDER BY waiting DESC;

-- N. Repeat customer rate
SELECT
  COUNT(DISTINCT CASE WHEN order_count = 1 THEN email END) AS one_time,
  COUNT(DISTINCT CASE WHEN order_count = 2 THEN email END) AS two_orders,
  COUNT(DISTINCT CASE WHEN order_count >= 3 THEN email END) AS three_plus
FROM (
  SELECT email, COUNT(*) AS order_count
  FROM orders
  WHERE payment_status = 'paid'
  GROUP BY email
) sub;

-- R. Coupon leaderboard
SELECT coupon_code,
       COUNT(*) AS times_used,
       SUM(discount_total) AS total_discounted,
       AVG(discount_total) AS avg_discount
FROM order_coupon_usages
GROUP BY coupon_code
ORDER BY total_discounted DESC;

-- V. Cart abandonment funnel
SELECT
  COUNT(*) AS abandoned,
  SUM(reminder_sent_at IS NOT NULL) AS reminded,
  SUM(recovered_at IS NOT NULL) AS recovered,
  ROUND(SUM(recovered_at IS NOT NULL) / NULLIF(SUM(reminder_sent_at IS NOT NULL), 0) * 100, 1) AS recovery_pct
FROM cart_abandonments;
```

---

### 10.10 Summary: Metrics by Business Question

| Business Question | Metric(s) | Section |
|---|---|---|
| "Are we growing?" | Revenue trend, new customers, order count | A, L |
| "What's selling?" | Top products, top variants, units moved | F, G |
| "Do we need to restock?" | Stock health, alert demand, out-of-stock count | H, I |
| "Where are our customers?" | Country breakdown (orders + users) | D, M |
| "Are discounts helping or hurting?" | Discount margin %, coupon leaderboard, AOV trend | R, S, B |
| "Are bundles worth it?" | Bundle order count, bundle discount total, AOV comparison | T |
| "Is cart recovery working?" | Funnel (abandoned → reminded → recovered), recovery revenue | V, W |
| "Are customers coming back?" | Repeat rate, order frequency distribution | N |
| "Which artists/brands have demand?" | Tag follow counts, tag notification volume | P |
| "Is our email list healthy?" | Subscriber count, growth rate, unsubscribe trend | Q |
| "What needs attention right now?" | Pending orders, low stock, active stock alerts | Overview KPIs |
5. **Minimal Steps** — Every unnecessary page load or click is a chance to lose the user
6. **Dashboard First** — Admin sees the big picture before diving into details
7. **Mobile First** — Cart and checkout must work without horizontal scroll
8. **Color with Purpose** — Every color carries meaning (status, actions, emphasis)
