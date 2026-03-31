# GoonsGear Deep App Audit

Date: 2026-04-01

Scope: Laravel core, application architecture, custom ecommerce code, WooCommerce import path, product variation behavior, media handling, cart and checkout, staging pipeline, and current test health.

Status legend:
- [x] Healthy or acceptable
- [!] Verified issue or material risk
- [-] Mixed: workable but needs cleanup
- [ ] Missing or not yet implemented

## Executive Summary

This application is a custom Laravel 12 ecommerce build with a solid base, a real deployment pipeline, and enough test coverage to prove that the main catalog/cart/checkout path exists and mostly works.

The product variation system is the weakest part of the current architecture.

The core problem is not one bad query or one broken view. The problem is that the application has three different ideas of what a variant is:

1. A purchasable record with price, stock, and SKU.
2. A structured option record with size/color semantics.
3. A legacy-imported WooCommerce attribute record.

Those three ideas are only partially connected. The database supports them, some helper code assumes them, but the admin UI, import pipeline, storefront rendering, and test coverage do not enforce the same contract.

That mismatch is the most likely reason the variant logic keeps failing.

## Scorecard

- [x] Laravel foundation is current and conventional: Laravel 12.56, PHP 8.3, route setup in `bootstrap/app.php`.
- [x] Core ecommerce domain exists in first-party code: products, variants, media, cart, checkout, coupons, orders, stock alerts.
- [x] Staging deployment pipeline exists and includes asset build, Composer install, migrations, cache clear, cache rebuild, and post-deploy checks.
- [x] Cart and checkout persist and process `product_variant_id` correctly as the commercial unit of sale.
- [-] Catalog architecture is understandable, but variant logic is scattered across models, controllers, Blade, JS, import commands, and observers.
- [!] Variant typing is incomplete and inconsistent across create, update, import, display, and repair workflows.
- [!] Imported WooCommerce variants do not preserve structured attribute semantics in a reliable first-class way.
- [!] The custom command intended to repair variant typing is broken PHP and cannot be trusted.
- [!] Test coverage is decent for CRUD and happy paths, but weak where the variant system is actually most fragile.
- [!] At least one relevant storefront test currently fails.

## Environment Baseline

- [x] PHP 8.3
- [x] Laravel 12.56.0
- [x] MySQL application database
- [x] Livewire 4 is installed
- [-] The application mostly uses Blade plus custom JavaScript, not a consistent Livewire-driven interactive strategy
- [x] Staging deployment workflow exists in `.github/workflows/deploy-stage.yml`
- [x] Boost tooling is present and working

## Architecture Review

### 1. Foundation and Framework Use

- [x] The app follows Laravel 12 structure correctly.
- [x] Routes are centralized in `routes/web.php` and middleware aliases are registered in `bootstrap/app.php`.
- [x] Authorization and admin routing are structurally clean.
- [-] Some business logic is still too controller-heavy instead of being isolated in focused actions/services.

Notes:
- `App\Actions\Checkout\CreateOrderAction` is a good sign. It shows the app already moved some core logic into an action class.
- The catalog and variant domain has not followed that same pattern. Variant behavior is spread across:
  - `app/Models/ProductVariant.php`
  - `app/Http/Controllers/Admin/ProductVariantController.php`
  - `app/Http/Controllers/ShopController.php`
  - `resources/views/shop/show.blade.php`
  - `resources/js/app.js`
  - `app/Console/Commands/ImportLegacyData.php`
  - `app/Console/Commands/AssignVariantTypes.php`

Assessment:
- [-] Good Laravel base
- [!] Variant domain too distributed to reason about safely

### 2. Custom Code Density

- [x] The app is intentionally custom and not over-dependent on packages.
- [x] That gives you control over migration from WooCommerce.
- [!] It also means correctness depends almost entirely on your own domain contract being explicit.

Current state:
- Products own many variants.
- Variants carry the price, SKU, inventory, preorder flags, and order linkage.
- Media can belong to a product or to a specific variant.
- Cart, checkout, orders, stock alerts, bundle discounts, and notifications all use `product_variant_id`.

That is the right commercial unit.

The problem is that the app is strong on "variant as purchasable row" and weak on "variant as structured option system".

## Product Variation Audit

### 1. Data Model

Files reviewed:
- `app/Models/Product.php`
- `app/Models/ProductVariant.php`
- `database/migrations/2026_03_28_212609_create_product_variants_table.php`
- `database/migrations/2026_03_31_194852_add_variant_type_to_product_variants_table.php`

Status:
- [x] `products` and `product_variants` are separated correctly.
- [x] `product_variants` is the transactional unit used by cart, checkout, stock alerts, and orders.
- [x] Unique SKU enforcement exists.
- [x] Unique `(product_id, name)` constraint exists.
- [-] `option_values` exists as JSON, but no stable schema is enforced.
- [-] `variant_type` exists, but the rest of the system does not consistently create or use it.
- [!] The `variant_type` migration has no rollback logic in `down()`.

Why this matters:
- The app can store structured variant meaning, but it does not guarantee it.
- Once data becomes partially typed and partially freeform, every layer starts making guesses.

### 2. Variant Creation and Editing

Files reviewed:
- `app/Http/Controllers/Admin/ProductVariantController.php`
- `app/Http/Requests/StoreProductVariantRequest.php`
- `app/Http/Requests/UpdateProductVariantRequest.php`
- `resources/views/admin/products/variants/form-fields.blade.php`

Status:
- [x] Admin CRUD for variants exists.
- [x] Required validation for name, SKU, and price exists.
- [x] Duplicate name per product is prevented.
- [-] Admin supports raw `option_values_json` input.
- [!] Admin does not expose `variant_type`.
- [!] Admin does not derive `variant_type` from user input.
- [!] Admin does not validate the structure of `option_values` beyond "valid JSON string".

Observed contract gap:
- A variant can be created as:
  - name: `XL / Black`
  - option_values: `{ "size": "XL", "color": "Black" }`
  - variant_type: missing or defaulted to `custom`

That means the system stores semantic data but does not normalize it into a usable domain model.

Assessment:
- [!] Variant authoring is too freeform for a system that now expects typed behavior

### 3. Variant Typing Logic

Files reviewed:
- `app/Models/ProductVariant.php`
- `app/Console/Commands/AssignVariantTypes.php`

Status:
- [-] `ProductVariant::detectTypeFromName()` exists.
- [!] That method is simplistic and does not represent combination variants correctly.
- [!] That method is not wired into create, update, or import flows.
- [!] The dedicated repair command `variants:assign-types` is syntactically broken.

Verified runtime check:
- `php -l app/Console/Commands/AssignVariantTypes.php`
- Result: parse error, unexpected token `private`

Implication:
- The one custom recovery path that looks intended to classify legacy/imported variants is not runnable.
- This is not theoretical. It is a concrete broken maintenance tool.

Assessment:
- [!] High-confidence defect

### 4. Storefront Variant UX

Files reviewed:
- `app/Http/Controllers/ShopController.php`
- `resources/views/shop/show.blade.php`
- `resources/js/app.js`
- `resources/js/combo-variants.js`

Status:
- [x] Product page loads active variants and variant-aware stock state.
- [x] Variant selection updates price, SKU, quantity, selected cart variant ID, stock-alert form visibility, and gallery filter.
- [-] Current source uses a generic select-based variant picker.
- [!] The richer size/color combo approach is only partially integrated.
- [!] `combo-variants.js` expects `data-size-select`, `data-color-select`, and `data-combo-matrix`, but is not imported by `resources/js/app.js`.
- [!] No current source under `resources/` was found rendering those selectors.

What this means:
- There was at least one attempt to evolve the storefront into a typed size/color picker.
- That implementation did not become the single source of truth.
- The repo now contains evidence of two competing variant UI approaches.

Assessment:
- [!] Strong sign of partially abandoned variant refactor

### 5. Cart and Checkout Use of Variants

Files reviewed:
- `app/Http/Controllers/CartController.php`
- `app/Http/Controllers/CheckoutController.php`
- `app/Actions/Checkout/CreateOrderAction.php`

Status:
- [x] Cart stores items keyed by variant ID.
- [x] Checkout validates that variant IDs still exist and are active.
- [x] Orders store both `product_id` and `product_variant_id`.
- [x] Stock decrementation happens from the variant level.
- [!] Inventory update path does not lock variants for update before validating and decrementing stock.

Why that matters:
- Under concurrent checkouts, two requests can validate the same stock before either decrement finishes.
- That can oversell inventory.
- This is a custom-code architecture issue, not a WooCommerce import issue.

Assessment:
- [-] Functionally coherent
- [!] Concurrency risk remains

### 6. Variant-Specific Media

Files reviewed:
- `app/Models/ProductMedia.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/ProductMediaController.php`
- `app/Http/Controllers/ShopController.php`

Status:
- [x] Media can be attached to a specific variant.
- [x] Product edit flow can upload media for a selected variant.
- [x] Storefront gallery can filter by variant ID.
- [-] Cart item image always uses product-level primary media, not selected variant media.
- [!] Media deletion removes the primary stored path only.
- [!] Derived image variants and fallback originals are not cleaned up when media is deleted.

Implication:
- Even when variant media is attached correctly, downstream consumers do not consistently honor it.
- Storage can accumulate orphaned image derivatives over time.

Assessment:
- [-] Useful feature exists
- [!] End-to-end consistency is incomplete

## WooCommerce Import Audit

Files reviewed:
- `app/Console/Commands/ImportLegacyData.php`
- `tests/Feature/LegacyImportCommandTest.php`

Status:
- [x] Import pipeline exists for categories, tags, products, variants, customers, and orders.
- [x] Mapping tables for legacy IDs exist.
- [x] Reconciliation logic for missing mappings exists.
- [x] Simple product default variants are created.
- [x] Price recovery from legacy order history is covered by tests.
- [x] Future preorder dates are imported and covered by tests.
- [!] Imported variants do not persist structured WooCommerce attribute semantics into `option_values`.
- [!] Imported variants do not reliably populate `variant_type`.
- [!] Variant names remain the practical source of meaning after import.

This is a major root-cause candidate.

If WooCommerce data originally expressed variants as attributes like:
- size = XL
- color = Black

but the Laravel app mostly ends up with:
- name = `Black, XL`
- variant_type = missing or `custom`
- option_values = null

then every typed storefront or admin behavior becomes guesswork.

Assessment:
- [x] Import is good at moving rows
- [!] Import is weak at preserving variant semantics

## Testing Audit

Tests reviewed and/or run:
- `tests/Feature/Admin/ProductVariantCrudTest.php`
- `tests/Feature/ShopProductPresentationTest.php`
- `tests/Feature/LegacyImportCommandTest.php`
- `tests/Feature/CartFlowTest.php`
- `tests/Feature/CheckoutFlowTest.php`

Targeted test run result:
- 25 passed
- 1 failed

Failing test:
- `Tests\Feature\ShopProductPresentationTest::test_shop_show_prioritizes_preorder_status_and_displays_availability_date`

Status:
- [x] There is real feature coverage for admin CRUD, import, shop, cart, and checkout.
- [x] Tests validate several meaningful business rules.
- [!] There is no meaningful coverage for `variant_type` behavior.
- [!] There is no meaningful coverage for `option_values`-driven UI behavior.
- [!] There is no meaningful coverage for combo size/color selection.
- [!] There is no test coverage for the broken `AssignVariantTypes` command.
- [!] A storefront preorder presentation test currently fails.

Interpretation:
- The test suite proves the app is not entirely unstable.
- It also proves the most fragile part of the variant system is not fully under test.

## Operational and Deployment Audit

File reviewed:
- `.github/workflows/deploy-stage.yml`

Status:
- [x] Deployment to staging is automated through GitHub Actions.
- [x] Node assets are built before deploy.
- [x] Composer production install is part of pipeline.
- [x] Migrations are applied on deploy.
- [x] A post-migration pending-check exists.
- [x] Cache clear and optimize steps exist.
- [-] Deployment is good for shipping code, but not yet for proving variant integrity after import or after catalog changes.

Recommended operational gap closure:
- [ ] Add a post-deploy smoke test for at least one product with multiple variants.
- [ ] Add a staging integrity command or test to verify imported products have usable variant structure.

## Most Important Verified Findings

### High Severity

1. [!] Variant typing is not a reliable system contract.

Evidence:
- `variant_type` exists in schema and model.
- Admin create/update does not manage it.
- Import does not meaningfully populate it.
- Storefront source does not consistently depend on it.

Impact:
- Any feature that assumes typed variants can fail or drift per product.

2. [!] `variants:assign-types` is broken PHP.

Evidence:
- `php -l app/Console/Commands/AssignVariantTypes.php` failed with a parse error.

Impact:
- The obvious repair tool for legacy variant classification is unusable.

3. [!] WooCommerce import preserves variant rows better than variant meaning.

Evidence:
- Import creates and reconciles variants but does not reconstruct first-class attribute semantics into a normalized contract.

Impact:
- Storefront/admin logic becomes name-parsing logic.

### Medium Severity

4. [!] Variant logic is split across too many layers.

Impact:
- Small changes become risky.
- Bugs are likely to be fixed in one layer and left unfixed elsewhere.

5. [!] Checkout stock decrement has a race-condition risk.

Impact:
- Potential overselling under concurrent purchases.

6. [!] Variant-specific media is only partially honored across the user journey.

Impact:
- User may see the wrong image even when variant media exists.

7. [!] Media deletion leaves likely derivative/original file residue.

Impact:
- Storage drift and harder media integrity maintenance.

### Lower Severity but Worth Fixing

8. [!] `variant_type` migration cannot be rolled back cleanly.

9. [-] The app contains dead or abandoned code paths around combo variants.

10. [!] Relevant storefront preorder presentation test is failing.

## Root Cause Statement

The current product variation failures are most likely caused by an incomplete transition from a flat variant model to a structured variant model.

The application started with a valid simple rule:
- a variant is just a purchasable SKU row

Then the app began adding richer semantics:
- size
- color
- combo selection
- variant-specific media
- legacy WooCommerce attribute recovery

But those semantics were never fully made authoritative across:
- schema
- import
- admin authoring
- storefront rendering
- JavaScript behavior
- repair tools
- tests

That is why the system feels unstable: different parts of the app are using different definitions of what a variant means.

## Recommended Remediation Plan

### Phase 1: Stabilize the Contract

- [ ] Choose one authoritative variant model.
- [ ] Decide whether variants are:
  - generic rows only, or
  - typed option rows, or
  - normalized combinations of dimensions
- [ ] Stop relying on freeform `name` parsing as the business contract.
- [ ] Define the exact schema for `option_values`.
- [ ] Make `variant_type` either truly authoritative or remove it from the domain.

Expected outcome:
- every product variant is representable the same way in admin, import, storefront, and checkout.

### Phase 2: Repair the Data Path

- [ ] Fix `AssignVariantTypes` or replace it with a tested repair command.
- [ ] Update import so legacy attributes map into normalized `option_values`.
- [ ] Backfill existing imported variants into the chosen contract.
- [ ] Add a report command that flags products with ambiguous variant data.

Expected outcome:
- imported WooCommerce products stop being special cases.

### Phase 3: Simplify the UI Path

- [ ] Remove dead combo-variant code or finish integrating it.
- [ ] Make storefront use the same domain contract the admin writes.
- [ ] Make cart preview honor variant-specific media where available.
- [ ] Add explicit product-level rules for simple products versus multi-option products.

Expected outcome:
- variant behavior becomes predictable for both admins and shoppers.

### Phase 4: Close the Test Gaps

- [ ] Add tests for typed size variants.
- [ ] Add tests for typed color variants.
- [ ] Add tests for combo variants.
- [ ] Add tests for import-to-variant-structure mapping.
- [ ] Add tests for variant-specific media selection.
- [ ] Add tests for stock decrement under concurrency-sensitive conditions.

Expected outcome:
- future regressions are caught before staging.

## Immediate Next Steps

If this audit is the basis for cleanup work, the first practical sequence should be:

1. Fix or replace the broken variant typing command.
2. Define the authoritative stored shape for variant options.
3. Audit a sample of imported products and classify them into:
   - simple/default only
   - size only
   - color only
   - size plus color combos
   - ambiguous/manual review
4. Make import and admin creation both produce that exact shape.
5. Update storefront rendering to consume only that shape.
6. Add tests before refactoring the remaining UI.

## Storefront Readiness Audit

Evidence reviewed:
- `php artisan route:list --except-vendor --no-interaction`
- `tests/Feature/ShopBrowseTest.php`
- `tests/Feature/ShopPaginationTest.php`
- `tests/Feature/ShopProductPresentationTest.php`
- `tests/Feature/CartFlowTest.php`
- `tests/Feature/CheckoutFlowTest.php`
- `tests/Feature/CheckoutPayPalFlowTest.php`
- `tests/Feature/MediaDeliveryTest.php`

Status:
- [x] The store has the basic customer-facing flow: browse, category view, product view, cart, checkout, success page, account area, search endpoint, and media delivery.
- [x] Product, cart, checkout, media, category, tag, and search routes exist.
- [x] There is basic feature-test coverage for browsing, pagination, product presentation, cart, checkout, PayPal flow, and media delivery.
- [-] Storefront basics exist, but some of them are only proven as route/view behavior rather than robust business workflows.
- [!] A relevant storefront product-page test is currently failing.
- [!] The storefront still depends heavily on custom Blade and DOM-coupled JavaScript in its most complex area.

What the store appears to have at a basic level:
- Catalog browsing
- Category filtering
- Tag filtering
- Product detail pages
- Variant selection
- Cart operations
- Coupon application
- Checkout and PayPal flow
- User account basics
- Stock alerts
- Redirect support for legacy URLs

What is still missing or weak for a store that should run reliably:
- [!] No strong evidence of a stable product-option contract for multi-variant products.
- [!] No post-deploy store smoke test to prove browse-to-buy on staging after pushes.
- [!] No visible admin-driven import/reconciliation workflow for catalog repair; this still depends on commands and helper scripts.
- [!] No clear evidence of a customer/admin management surface beyond account self-service and basic admin access rules.
- [-] No project-specific README or operating guide; the root `README.md` is still the default Laravel placeholder.

Assessment:
- [-] The store covers the baseline shopper journey.
- [!] The fragile area is not store existence, but store reliability under real catalog complexity.

## Admin Panel Coverage Audit

Evidence reviewed:
- Admin routes from `routes/web.php`
- Admin controllers under `app/Http/Controllers/Admin`
- Admin tests including `AdminAccessControlTest`, `ProductCrudTest`, `ProductVariantCrudTest`, `OrderDashboardTest`, `IntegrationSettingsTest`, `CategoryCrudTest`, `CouponManagementTest`

Status:
- [x] The admin panel covers categories, products, variants, product media, coupons, bundle discounts, regional discounts, tags, orders, redirects, fallback media, integrations, abandoned-cart maintenance, cache clearing, and log clearing.
- [x] Admin access control is covered by tests.
- [-] Admin coverage is broad enough for day-one operations, but not yet complete for safe long-term catalog operations.
- [!] The admin does not provide a first-class way to manage structured variant semantics.
- [!] The admin exposes raw JSON for `option_values`, which is powerful but not safe for routine operations.
- [!] The admin does not expose a clear workflow for import validation, variant repair, or data integrity review.
- [!] There is no evident admin surface for user/customer management, import oversight, catalog diagnostics, or bulk catalog operations.

What the admin covers reasonably well:
- Basic catalog CRUD
- Order status and tracking updates
- Coupon and discount management
- Redirect management
- Some operational settings and maintenance tools

What the admin is lacking even though it should likely exist for this store:
- [ ] Variant integrity diagnostics
- [ ] Import status and repair tooling
- [ ] Bulk inventory/catalog operations
- [ ] Customer/admin review tools for support workflows
- [ ] Safer UX for complex variant authoring
- [ ] Store health dashboard for catalog, media, and integration failures

Assessment:
- [-] The admin is useful and not empty.
- [!] It is still too developer-dependent for several normal store-maintenance tasks.

## Git and Delivery Audit

Evidence reviewed:
- `git log --oneline --decorate -n 40`
- `.github/workflows/deploy-stage.yml`

Status:
- [x] GitHub Actions deployment to staging exists and is materially useful.
- [x] Deployment includes build, Composer install, migration checks, migration run, cache clear, and optimize.
- [-] Commit history shows active iteration and real progress.
- [!] Commit history also shows repeated clustered fixes around variants and media in a short span.
- [!] The recent history strongly suggests partial refactors and follow-up fixes landing in the same problem area.

Signals from recent history:
- Multiple consecutive commits target variant UX, combo parsing, display cleanup, and type assignment.
- Several commits also target media import and image handling in quick succession.
- That pattern usually means the system contract was still moving while implementation was already being layered on top of it.

Assessment:
- [-] Delivery capability exists.
- [!] Change quality in the catalog/variant area looks reactive rather than stabilized.

## Laravel Standards and Code Quality Audit

Evidence reviewed:
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/CouponController.php`
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Http/Controllers/Admin/MaintenanceController.php`
- `resources/views/shop/show.blade.php`
- `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`

Status:
- [x] Much of the codebase uses typed controllers, Form Requests, route model binding, Eloquent relationships, and Laravel-style routing.
- [x] Several admin areas follow Laravel conventions reasonably well.
- [-] The codebase is mixed: some areas are clean and conventional, while the catalog/variant area shows standards drift.
- [!] `ProductController` is 709 lines long.
- [!] `resources/views/shop/show.blade.php` is 589 lines long.
- [!] The product page template carries too much presentation logic and variant/UI branching.
- [!] `MaintenanceController` still uses inline validation instead of dedicated Form Requests.
- [!] The codebase currently cannot complete static analysis because `AssignVariantTypes.php` has syntax errors.

Laravel standards assessment by area:
- [x] Routing: generally good
- [x] Form Requests: used in many places, but not consistently
- [x] Eloquent relationships and casts: generally good
- [-] Controllers: mixed quality; some are slim, some are oversized
- [!] Blade views: too much conditional/business UI logic in critical templates
- [!] Commands/scripts: operationally important, but at least one is broken and there are many repo-level helper scripts outside normal app structure
- [-] Documentation/operability: weak; the default Laravel README is still present instead of project-specific operational documentation

Assessment:
- [-] The app broadly respects Laravel standards in structure.
- [!] The catalog/variant path does not consistently respect Laravel best practices around controller size, view complexity, and maintainable domain boundaries.

## Final Assessment

- [x] The app is not a random prototype. It has real structure and a workable foundation.
- [x] The deployment and testing story is much stronger than a typical throwaway migration project.
- [x] The storefront covers the basic shopper journey at the route and feature-test level.
- [-] The admin panel covers many essentials, but still leaves several important store-maintenance tasks developer-dependent.
- [!] The product variation domain is currently under-specified and partially implemented.
- [!] The biggest risk is semantic inconsistency, not missing CRUD.
- [!] Recent git history reinforces the same conclusion: repeated fixes are landing around an unstable contract.
- [!] Until the variant contract is made explicit and enforced end to end, new fixes in this area will likely keep producing diminishing returns.
