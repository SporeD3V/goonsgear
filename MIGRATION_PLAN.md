# GoonsGear WooCommerce → Laravel Migration Plan

## Constraints and Decisions
- **Source is archive + SQL dump only.** No live access to the all-inkl host during import work.
- **Dedicated mapping tables**, not in-model legacy ID columns.
- **Redirects** handled in the app via the `url_redirects` table.
- **Staging must not be indexed** until production go-live.
- **Orphan media cleanup** (images attached to no product) runs after import.

---

## Staging Legacy Import Database
Separate database — do not mix with the app runtime database.

| Field    | Value                  |
|----------|------------------------|
| Database | `LEGACYgoonsgearDB`    |
| User     | `LEGACYgoonsgearUSER`  |
| Password | `WSvlby6AftxXYxpWFddL` |

---

## Uploads Archive Coverage
The 2017–2026 year/month folders cover all product images and gallery media.

Other WP uploads folders that are **not needed** (unless confirmed otherwise):
- `woocommerce_uploads/` — downloadable product files
- `wpo_wcpdf/` — WooCommerce PDF invoices
- `elementor/`, `cache/` — page builder / plugin cache

**Action:** Before importing, check `LEGACYgoonsgearDB` for any product with `_downloadable = yes`. If found, the `woocommerce_uploads/` folder is needed too.

---

## Staging: Prevent Search Engine Indexing
Before importing any content, confirm staging is blocked from indexing:
- `public/robots.txt` must contain `Disallow: /`
- All page responses should include `X-Robots-Tag: noindex` header or `<meta name="robots" content="noindex">` in the layout

---

## Feature Gap Analysis

### Already Covered in Laravel Schema
| WooCommerce concept | Laravel mapping |
|---|---|
| Product name, slug, description, excerpt | `products`: name, slug, description, excerpt |
| Product status (draft/publish) | `products.status` |
| Meta title, meta description (Yoast) | `products`: meta_title, meta_description |
| Product categories (hierarchical) | `categories` table with parent_id |
| Product tags | `tags` + `product_tag` pivot |
| Product variants / variations | `product_variants` with option_values JSON |
| SKU, price, compare_at_price | `product_variants` |
| Inventory tracking, stock, backorders | `product_variants`: track_inventory, stock_quantity, allow_backorder |
| Product media / gallery | `product_media` with conversion pipeline |
| Variant-linked media | `product_media.product_variant_id` |
| Orders with address | `orders` with full address fields |
| Order items | `order_items` |
| Separate delivery/shipping address on users | `users`: delivery_* columns |
| Coupons with rules | `coupons` with scope_type/scope_id |
| Bundle discounts | `bundle_discounts` + `bundle_discount_items` |
| Regional discounts | `regional_discounts` |
| URL redirects (301) | `url_redirects` |
| PayPal payment tracking | `orders`: paypal_order_id, paypal_capture_id |
| Shipping tracking | `orders`: shipping_carrier, tracking_number, shipped_at |

### Gaps to Investigate and Resolve

| WooCommerce feature | Gap | Decision needed |
|---|---|---|
| Billing address vs shipping address on orders | Orders have one address set; WC has both | Determine if they differ for historical orders. May need `billing_*` columns on orders or a mapping note. |
| Order shipping cost as explicit line | No `shipping_total` column on orders | Add if needed for historical accuracy; can be inferred from total − subtotal − taxes − discounts |
| Order tax total | No `tax_total` column on orders | Decide whether to store imported tax as a column or absorb into totals |
| Order notes / customer note | Not stored in app | Add `customer_note` to orders or an `order_notes` table |
| Order status mapping | WC: pending, processing, on-hold, completed, cancelled, refunded, failed | Map to app statuses before import |
| Refunds | WC has refund line items | Decide: import as negative line items or a status flag only |
| Product weight and dimensions | Not in schema: _weight, _length, _width, _height | Needed for live shipping rate calculation; add columns if required |
| Product reviews / ratings | Not in schema | Decide: import WC reviews or start fresh |
| User first_name and last_name | Users table has single `name` field | WC stores them separately; decide whether to split on import |
| User billing address | Users table has delivery_* but no billing_* | WC has separate billing address on users; decide if needed |
| Downloadable products | Not in schema | Check legacy DB; if present, decide feature support before import |
| Bundle implementation | No plugin in legacy: bundles are normal standalone products | Import bundles as regular products; no bundle-plugin metadata parsing required |
| Category images | Categories have no image column | Add if needed; WC stores as term_meta |
| Cross-sells / upsells | Not in schema | Decide: import relationship data, store as JSON, or skip |

---

## Legacy DB Audit Findings (2026-03-31)

Direct audit of `LEGACYgoonsgearDB` produced these confirmed facts:

### Core Volumes
- Products: `1161`
- Variations: `2280`
- Orders: `13967`
- Refund posts: `193` (`shop_order_refund`)

### Orders and Checkout Behavior
- Order statuses in use:
   - `wc-completed` (13674)
   - `wc-pre-ordered` (130)
   - `wc-refunded` (119)
   - `wc-cancelled` (19)
   - `wc-processing` (14)
   - `wc-failed` (11)
- Payment methods in use:
   - `paypal_plus` (8441)
   - `ppcp-gateway` (4072)
   - `stripe` (1196)
   - `ppcp-credit-card-gateway` (258)
- Shipping methods in use:
   - `flexible_shipping_single` (12838)
   - `flexible_shipping` (1243)
   - `free_shipping` (12)
- Order item type usage:
   - `line_item`: 27786
   - `shipping`: 14093
   - `coupon`: 2090
   - `tax`: 907
- Financial meta coverage:
   - `_order_shipping`: present on all orders
   - `_order_tax`: present on all orders
   - `_cart_discount`: present on all orders
   - `_customer_note`: effectively unused

### Product/Media Behavior
- Products without `_thumbnail_id`: `104`
- `_product_image_gallery` present for `956` products
- No downloadable products (`_downloadable = yes` count: `0`)
- Weight/dimensions are used heavily:
   - `_weight`: 713 products
   - `_length/_width/_height`: 711 products each
- Upsell/cross-sell usage: none detected (`0` / `0`)
- Category thumbnails are used: `15` product categories have `thumbnail_id`

### Compatibility/Plugin Signals
- HPOS table usage (`wp_wc_orders` with `shop_order`): `0` (legacy post-based order model)
- Discount Rules plugin tables exist (`wp_wdr_*`)
- Gift card tables exist (`wp_pimwick_gift_card*`)
- Woo shipping/tracking plugin tables exist (`wp_woocommerce_stc_*`)

### Address Divergence
- Billing and shipping differ for a small but real subset of orders:
   - first name: 19
   - last name: 13
   - address line 1: 66
   - city: 31
   - postcode: 38
   - country: 7

### Schema Decisions — Confirmed

**Payment Methods & Order History:**
- Keep `payment_method` field on orders for historical reference (PayPal only going forward at runtime, but orders show how they were paid).
- Preserve all historical payment method values (paypal_plus, ppcp-gateway, stripe, ppcp-credit-card-gateway) in imported orders.

**Orders Financial Data (REQUIRED):**
- Add `shipping_total` to orders (currently missing; _order_shipping meta present on all orders).
- Add `tax_total` to orders (currently missing; _order_tax meta present on all orders).
- Discount totals are already tracked in `discount_total` and `regional_discount_total`.

**Product Dimensions (REQUIRED):**
- Add to products table: `weight`, `length`, `width`, `height` (used on ~700 products; needed for shipping calculations).

**Billing vs Shipping Address (REQUIRED):**
- Add billing address columns to orders table (or child table) to preserve the 66–1000 orders where billing ≠ shipping.
- Capture: billing_first_name, billing_last_name, billing_street_name, billing_street_number, billing_apartment_*, billing_city, billing_postal_code, billing_country, billing_state.

**Product Images:**
- Product images will follow the app's existing SEO-inspired naming structure (not imported from WC category hierarchy).
- No category images import (respect app's own image management).

**Image URL Redirects (OPTIONAL):**
- Old WC image URLs will have different paths; redirects should be preserved separately.
- Recommendation: archive old image paths in a `legacy_redirects` table or similar, and serve 301 redirects for backward compatibility.

**Order Discounts & Gift Cards:**
- Discount totals are already reflected in orders as `_cart_discount` meta (all orders have this).
- Import discount totals as `discount_total` (already in schema).
- Gift card history / discount rule plugin data NOT required (history does not affect order totals).

---

## Dedicated Mapping Tables

Create in the app database before importing. These allow idempotent, repeatable imports.

```sql
import_legacy_products     (id, legacy_wp_post_id, product_id, synced_at)
import_legacy_variants     (id, legacy_wp_post_id, product_variant_id, synced_at)
import_legacy_categories   (id, legacy_term_id, category_id, synced_at)
import_legacy_tags         (id, legacy_term_id, tag_id, synced_at)
import_legacy_customers    (id, legacy_wp_user_id, user_id, synced_at)
import_legacy_orders       (id, legacy_wc_order_id, order_id, synced_at)
import_legacy_media        (id, legacy_wp_attachment_id, product_media_id, legacy_path, file_hash, synced_at)
```

---

## Migration Phases

### Phase 1: Staging Environment Preparation
1. Import SQL dump into `LEGACYgoonsgearDB`.
2. Extract media archive to `/home/macaw-goonsgear/legacy/wp-uploads/` (immutable; never modify this source).
3. Confirm staging noindex is in place.
4. Run gap analysis queries against `LEGACYgoonsgearDB`:
   - Check for downloadable products.
   - Confirm bundle SKUs are standalone products (no plugin dependencies).
   - Check billing vs shipping address divergence in historic orders.
   - Count products, variants, orders.
5. Decide on each Gap item above before writing import code.

Acceptance criteria:
- `LEGACYgoonsgearDB` accessible and queryable.
- Media archive intact with year/month structure.
- Gap decisions documented.

### Phase 2: Mapping Migrations and Schema Additions
1. Create mapping table migrations.
2. Apply any schema additions agreed in gap analysis (e.g. order notes, shipping_total, tax_total).
3. All migrations run cleanly.

Acceptance criteria:
- All mapping tables exist.
- Schema additions merged and tested.

### Phase 2.5: Pre-Import Demo Data Cleanup (CRITICAL)
Before running any import jobs, truncate all demo/example data to ensure a clean slate:

```sql
-- Disable foreign keys to avoid constraint violations
SET FOREIGN_KEY_CHECKS = 0;

-- Truncate demo data
TRUNCATE TABLE category_product;
TRUNCATE TABLE product_tag;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE products;
TRUNCATE TABLE product_variants;
TRUNCATE TABLE tags;
TRUNCATE TABLE categories;
TRUNCATE TABLE product_media;

-- Reset auto-increment counters
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE product_variants AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE tags AUTO_INCREMENT = 1;

-- Re-enable foreign keys
SET FOREIGN_KEY_CHECKS = 1;
```

Acceptance criteria:
- All demo categories, products, variants, orders, and media removed.
- No orphaned foreign key references remain.
- Mapping tables remain intact (not truncated).

### Phase 3: Data Import (Iterative, Idempotent)
Import order must respect foreign keys:

1. Categories (hierarchical — import parents before children)
2. Tags
3. Products (simple and variable)
4. Product variants
5. Bundle relationships (if represented in your app, create manually from agreed product mappings)
6. Customers (users)
7. Historical orders and order items

Each import job must:
- Upsert by legacy ID via mapping table
- Log failures per row
- Be safely re-runnable without duplicating records

Acceptance criteria:
- Import runs without fatal interruption.
- Re-run produces no duplicates.
- Failure log is queryable.

### Phase 4: Media Ingestion and Conversion
1. For each imported product, read legacy media attachments from `LEGACYgoonsgearDB` (`wp_postmeta._thumbnail_id`, `_product_image_gallery`).
2. Locate source file in `/home/macaw-goonsgear/legacy/wp-uploads/`.
3. Copy to app storage and register in `product_media`.
4. Queue conversion job: AVIF → WebP → original.
5. Track conversion status per file.
6. After all conversions: delete `product_media` records (and their files) where the linked product no longer exists — **orphan cleanup**.

Acceptance criteria:
- All product pages render images or the placeholder.
- Conversion status report shows 0 unresolved failures.
- No orphaned media files remain.

### Phase 5: SEO Redirects
1. Extract all indexable legacy URLs from `LEGACYgoonsgearDB`:
   - Product URLs: `/{slug}/`
   - Category URLs: `/product-category/{slug}/`
   - Tag URLs: `/product-tag/{slug}/`
   - Any blog/page URLs that need preserving
2. Generate redirect map using legacy slug → new app route.
3. Seed into `url_redirects` table (status_code = 301).
4. Validate: no redirect chains, top-page URLs resolve correctly.

Acceptance criteria:
- Critical legacy URLs redirect with 301.
- No chains (A → B → C).
- Canonical meta on product/category pages points to new URL.

### Phase 6: Quality Checks
Run reconciliation before declaring staging ready:

- [ ] Product count matches legacy (simple + variable as parent posts)
- [ ] Variant count matches legacy product_variations
- [ ] Order count matches legacy (within agreed date range)
- [ ] Revenue parity check for a sample month
- [ ] No product with missing primary image (or confirmed no image in legacy)
- [ ] Orphan media = 0
- [ ] Redirects resolve for top 20 legacy product URLs
- [ ] Staging noindex confirmed via curl headers

### Phase 7: Delta Sync Before Go-Live
> Only applicable once a launch date is set and the live WC site is still taking orders.

1. Agree a `last_synced_at` checkpoint with client.
2. Request a new DB export covering only changes after checkpoint, OR a full re-dump.
3. Re-run importer (idempotent — only upserts, no duplicates).
4. Sync any new media files individually.
5. Rehearse delta at least once before final cutover.

### Phase 8: Cutover
1. Client announces content freeze on WooCommerce.
2. Request final DB export and any new media.
3. Run final delta sync.
4. Drain conversion queue.
5. Switch DNS / traffic.
6. Remove noindex from staging (now production).
7. Monitor for 24–48h: checkout, errors, queue depth, 404s.

---

## Operational Guardrails
- Never modify the legacy DB dump or media archive in place — keep as immutable source.
- Import jobs must be chunked, retryable, and idempotent.
- Conversion workers must be throttled to avoid OOM on staging server.
- Dead-letter handling for conversion failures — log and retry, do not silently skip.

## Rollback Readiness
- Keep a DB snapshot taken immediately before cutover.
- Keep DNS TTL low (60s) before cutover day.
- If critical regression: revert DNS, restore snapshot, investigate offline.
