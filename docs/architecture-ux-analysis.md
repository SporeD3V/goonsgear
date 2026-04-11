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

### Phase 1: Admin Container Separation (High Impact, Low Risk)
1. **Product edit page** — Break single form into sectioned containers
2. **Order detail page** — Separate info cards, actions, and items into containers
3. **All list pages** — Separate filters and data table into distinct containers
4. **Sidebar reorganization** — Flat sections with labels

### Phase 2: Admin Dashboard
4. **Create admin dashboard** — Landing page with quick stats and attention items

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
5. **Minimal Steps** — Every unnecessary page load or click is a chance to lose the user
6. **Dashboard First** — Admin sees the big picture before diving into details
7. **Mobile First** — Cart and checkout must work without horizontal scroll
8. **Color with Purpose** — Every color carries meaning (status, actions, emphasis)
