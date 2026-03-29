# Test Run Checklist (Next Day)

Run this checklist after pulling the latest `main` branch.

## Important: Development Server Required

If you're using Apache (`localhost`), you'll need to use the **development server** for testing. The Laravel development server properly handles all route rewriting.

**Start the dev server:**
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Then visit: `http://127.0.0.1:8000/shop` instead of `http://localhost/shop`

## 1) Automated tests (recommended order)

### A. Focused storefront suite (fast)

```bash
php artisan test --compact tests/Feature/ShopBrowseTest.php
```

Expected: all tests pass.

### B. Media/admin regression suites

```bash
php artisan test --compact tests/Feature/Admin/ProductCrudTest.php
php artisan test --compact tests/Feature/Admin/FallbackMediaControllerTest.php
php artisan test --compact tests/Feature/MaintenanceControllerTest.php
php artisan test --compact tests/Feature/MediaDeliveryTest.php
php artisan test --compact tests/Feature/FallbackMediaCommandTest.php
```

Expected: all pass, with possible skip for image conversion if server lacks `imagewebp` / `imageavif`.

### C. Full test suite

```bash
php artisan test --compact
```

Expected: all pass (plus known environment-dependent skips only).

## 2) Manual storefront checks

### A. Shop listing

Test at: `http://127.0.0.1:8000/shop` (and use this URL for all tests below)

- `/shop`
- `/shop?sort=name_asc`
- `/shop?sort=name_desc`
- `/shop?sort=price_asc`
- `/shop?sort=price_desc`
- `/shop?q=hoodie&category=featured&sort=price_asc`

Verify:
- Category/search/sort filters all work together.
- **Live search:** Type in search box, dropdown shows matching products in real-time.
- Pagination keeps selected query params.
- Product cards show primary media and `From $X.XX` when active variants exist.

### B. Category landing pages

- `/shop/category/{category-slug}` (for each active category)

Verify:
- Only products from that category are shown.
- Page `<title>` and meta description reflect category metadata when set.
- Inactive category URL returns 404.

### C. Product detail + variants + gallery

- `/shop/{active-product-slug}`

Verify:
- Hover/click/focus on thumbnails updates main media.
- Active thumbnail has visible highlight state.
- Variant dropdown updates price, SKU, stock status, and qty panel.
- Variant dropdown syncs gallery filter and visible media.
- Product page contains JSON-LD (`application/ld+json`) in page source.
- **Live search:** Test on shop page by typing in search (should show dropdown results).

## 3) Manual admin/media regression checks

### A. Product media actions

- Open product edit page in admin.
- Upload a new image and video (if available).
- Set non-primary media as primary.
- Delete a non-primary media item.

Verify:
- No 404/500 errors.
- Primary preview updates correctly.

### B. Fallback media maintenance

- Open fallback media page in admin.
- Apply filters: optimization missing, unknown product paths.
- Test actions: delete fallback, reconvert & apply.

Verify:
- Filters match expected rows.
- Status flash messages appear.
- Reconverted file is used by media row.

## 4) Deployment sanity checks (staging)

After deploy:

- Ensure new commits are present.
- If frontend changes are missing, run the normal build pipeline (`npm run build` or project deploy build step).
- Re-check:
  - `/shop`
  - `/shop/category/{slug}`
  - `/shop/{product-slug}`
  - Admin product edit media section

## 5) If something fails

Capture and share:

- Failing URL and exact action.
- Browser error text (status code + message).
- Laravel log snippet around failure timestamp.
- Output of failing test command.
