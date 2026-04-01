# GoonsGear Audit Checklist

Date: 2026-04-01

Use this file as the working checklist for the deep app review and remediation effort.

## 1. Foundation and Architecture

- [x] Confirm the authoritative domain model for products, variants, media, cart, and checkout.
- [x] Document where business logic currently lives: models, controllers, actions, observers, commands, views, and JS.
- [x] Identify code paths that should move into dedicated action/service classes.
- [x] Confirm route, middleware, and admin authorization boundaries.
- [x] Verify current Laravel 12 conventions are followed consistently.

Audit notes (2026-04-01, pass 10):
- Core domain model is centered on Product, ProductVariant, ProductMedia, Order, OrderItem, UserCartItem, and supporting discount/subscription models.
- Business logic is distributed across controllers, CreateOrderAction, model helpers, artisan commands, Blade shaping, and resources/js/app.js.
- High-value extraction targets: ShopController variant/media shaping, checkout context/pricing orchestration, and large frontend interaction blocks.
- Routing and authorization boundaries are explicit in routes and middleware aliases (auth/guest/admin/admin.noindex/throttle).
- Laravel 12 structure is in place via bootstrap/app.php middleware/routing configuration.

## 2. Product Variant Domain

- [x] Define the single source of truth for variant structure.
- [x] Decide whether `variant_type` is authoritative or should be removed from the business contract.
- [x] Define the exact schema for `option_values`.
- [x] Identify all places where variant meaning is inferred from `name`.
- [x] List all variant states the app must support: simple, size-only, color-only, combo, preorder, backorder.

Audit notes (2026-04-01, pass 10):
- Source of truth: option_values + inventory/preorder/backorder flags, with variant_type treated as helper metadata rather than authoritative contract.
- option_values contract: nullable object map of normalized keys (size, color, option_n) to non-empty scalar string values.
- Name inference still occurs in ShopController attribute extraction/classification, ProductVariant::detectTypeFromName, ImportLegacyData fallback classification, and AssignVariantTypes.
- Supported states confirmed in code/tests: simple default variant, size-only, color-only, combo/custom, preorder, and backorder-enabled variants.

## 3. Variant Data Flow

- [x] Trace variant creation from admin form to database.
- [x] Trace variant update flow from admin form to database.
- [x] Trace variant display flow on the product page.
- [x] Trace variant selection flow into cart.
- [x] Trace variant validation flow into checkout.
- [x] Trace stock decrement and post-purchase side effects.
- [x] Trace stock alert behavior for out-of-stock and back-in-stock cases.

Audit notes (2026-04-01, pass 10):
- Admin create/update uses ProductVariantController + form requests, with option_values_json parsed into option_values before persistence.
- Product page display uses ShopController variant/media shaping and JS-driven picker/lightbox behavior.
- Cart path validates active variant/product and quantity constraints, then persists session/user cart item data.
- Checkout revalidates variant availability/stock from database, then creates orders through CreateOrderAction.
- Post-purchase side effects include stock decrement, coupon usage updates, cart abandonment recovery closing, and delivery-address persistence.
- Stock alert flow enforces active+out-of-stock-only subscription rules and upsert behavior per user/variant.

## 4. Admin Variant Authoring

- [x] Review `ProductVariantController` for validation and normalization gaps.
- [x] Review `StoreProductVariantRequest` and `UpdateProductVariantRequest` for schema-level validation gaps.
- [x] Review admin variant Blade forms for missing fields and weak UX.
- [ ] Confirm whether admins can reliably create structured variants without raw JSON guesswork.
- [ ] Confirm whether variant-specific media attachment is intuitive and safe.

Audit notes (2026-04-01, pass 10):
- Validation baseline is present and authorization is enforced, but option_values still depends on raw JSON textarea input.
- Controller normalization is functional but can be hardened with explicit option_values schema normalization helpers.
- Admin form UX remains technical for non-developers due JSON-first attribute entry and limited guided structure.

## 5. Storefront Variant UX

- [x] Verify the live storefront variant picker implementation.
- [x] Identify dead or partially integrated variant UI code.
- [x] Confirm whether size/color/combo selection is actually supported end to end.
- [ ] Confirm gallery filtering follows the selected variant correctly.
- [ ] Confirm cart submission always uses the intended selected variant ID.
- [x] Confirm preorder messaging renders correctly for selected variants.

Audit notes (2026-04-01, pass 10):
- Variant picker and selection flows are implemented in app.js and exercised by storefront tests for size/color/combo/preorder presentation.
- Legacy/dead variant UI has been reduced, but gallery-to-selected-variant coupling and selected variant ID submission still need explicit end-to-end assertions.

## 5A. Storefront Basics

- [x] Confirm the shop index page works correctly for basic browsing.
- [x] Confirm product detail pages work correctly for simple products.
- [x] Confirm product detail pages work correctly for products with multiple variants.
- [x] Confirm category browsing works correctly.
- [x] Confirm search returns relevant active products only.
- [x] Confirm pagination works on shop and category pages.
- [x] Confirm out-of-stock behavior is correct on listing and detail pages.
- [x] Confirm cart add/update/remove works for normal shopper flows.
- [x] Confirm checkout works for a standard order end to end.
- [ ] Confirm legal/basic storefront essentials are present where required: contact, account, cart, checkout success, media delivery.
- [x] Confirm core SEO basics are present: title, description, canonical, product metadata.
- [ ] Confirm there are no obvious blockers to a shopper completing a basic purchase.

## 6. Media and Variant Media

- [x] Review product-level versus variant-level media rules.
- [x] Confirm storefront uses variant media where appropriate.
- [x] Confirm cart previews use the correct image source.
- [x] Review media primary-selection rules.
- [x] Review media deletion logic for orphaned derivatives and fallback originals.
- [x] Review responsive image conversion and storage behavior.

Audit notes (2026-04-01, pass 2):
- Storefront search/catalog/product gallery now use context-sized variants (search 200x200, catalog/product display 600x600, lightbox full-size).
- Cart item preview image now resolves to thumbnail-200x200 with fallback-safe behavior.
- Checkout success page thumbnail now resolves to thumbnail-200x200 with fallback-safe behavior.

Audit notes (2026-04-01, pass 7):
- Product media supports both product-level and variant-level linkage via product_variant_id; storefront emits variant metadata for gallery filtering.
- Primary media ordering is controlled by is_primary, position, id ordering in storefront queries.
- Legacy media association command generates converted AVIF/WebP plus cropped derivatives and keeps fallback originals.
- Fallback cleanup command deletes originals only when optimized gallery variants are present, with list and dry-run safety modes.

## 6A. Store Readiness Gaps

- [x] Identify what the storefront still lacks to function properly at a basic ecommerce level.
- [x] Identify any missing product information required for a customer to buy confidently.
- [x] Identify any missing admin controls required to maintain catalog quality.
- [x] Identify any missing operational controls required to keep the store healthy after launch.
- [x] Separate true launch blockers from improvements.

Audit notes (2026-04-01, pass 8):
- Launch blockers: checkout stock race condition risk (missing row locks), incomplete order-item historical snapshot depth, and unresolved server credential-rotation follow-up.
- Confidence gaps for buyers: limited visible shipping/tax expectation context before checkout and limited product-detail richness beyond variant/price/media for some catalog entries.
- Admin control gaps: import/repair workflows still rely heavily on scripts/commands rather than unified admin workflows with guardrails.
- Operational control gaps: fragmented deploy tooling, limited standardized release gates, and no single consolidated operational dashboard for import/media health.
- Improvements (non-blocking): richer product trust content, stronger observability around media/import jobs, and tighter pre-release smoke automation.

## 7. WooCommerce Import

- [x] Review legacy product import behavior.
- [x] Review legacy variant import behavior.
- [x] Confirm imported variants preserve meaningful attribute semantics.
- [x] Confirm mapping tables are sufficient for safe reruns.
- [x] Confirm simple product default variant logic is reliable.
- [x] Confirm preorder data import is correct.
- [x] Confirm order import maps variants correctly.

Audit notes (2026-04-01, pass 6):
- Legacy import coverage confirms mapped product reconciliation, simple product default variant reconciliation, preorder propagation, and variation attribute normalization into option_values.
- Mapping table behavior is exercised for products, variants, and orders via import_legacy_* assertions.
- Added dedicated assertion that legacy variation order items map to imported product_variant_id for mapped variation IDs.

## 8. Variant Repair and Maintenance Commands

- [ ] Fix the broken `AssignVariantTypes` command.
- [x] Decide whether that command should remain or be replaced.
- [ ] Add tests for any variant repair/backfill command.
- [ ] Add a reporting command for ambiguous variant data.
- [ ] Add a data integrity check for missing or malformed variant semantics.

Audit notes (2026-04-01, pass 7):
- Decision: replace current AssignVariantTypes approach with a safer, test-backed command that does not depend on live legacy DB availability for core classification.
- Current command still lacks automated tests and explicit ambiguity reporting output persistence.

## 8A. Admin Panel Coverage

- [x] List all important store capabilities that should be manageable from the admin panel.
- [x] Confirm whether products, variants, media, categories, tags, coupons, discounts, orders, redirects, and integrations are adequately covered.
- [x] Identify what catalog-maintenance tasks still require code, scripts, or direct database access.
- [x] Identify what storefront settings are missing from admin even though they should be routine operations.
- [x] Identify what import/repair/media tasks are too operationally fragile for non-developer use.
- [x] Identify where admin UX is too raw for safe day-to-day store management.

Audit notes (2026-04-01, pass 8):
- Covered in admin routes/controllers: categories, products, product variants, product media controls, tags, coupons, bundle/regional discounts, orders (index/show/update), URL redirects, fallback media maintenance, and integration settings.
- Partially covered: maintenance operations are available but distributed across multiple pages/actions and still depend on operator knowledge.
- Still script-dependent: legacy import orchestration, variant-type repair/analysis, and several media verification/remediation flows.
- Missing routine controls: centralized release readiness checks, safer guided import backfill workflows, and clearer admin-native reporting for data integrity anomalies.
- UX risk areas: operational actions are powerful but low-guidance; some tasks remain too technical for non-developer day-to-day execution.

## 9. Cart and Checkout Integrity

- [x] Confirm cart only accepts active variants from active products.
- [x] Confirm quantity limits reflect real inventory behavior.
- [x] Review checkout stock validation for concurrency risk.
- [x] Decide whether row locking is needed during order creation.
- [ ] Confirm order items preserve enough historical variant data.
- [x] Confirm preorder and backorder behavior is consistent between product page, cart, and checkout.

Audit notes (2026-04-01, pass 5):
- Cart and checkout both enforce active variant + active product checks before purchase flows continue.
- Inventory quantity limits are enforced with preorder/backorder exceptions in cart and revalidated again in checkout.
- Concurrency risk exists in order creation: stock is validated and decremented inside a transaction but variants are not row-locked, so simultaneous checkouts can still oversell.
- Decision: row locking is needed during order creation for tracked, non-backorder, non-preorder variants.
- Order item snapshot includes product_name, variant_name, sku, unit_price, quantity, line_total; still missing richer historical snapshot fields (e.g., option_values/variant_type/media snapshot), so this remains open.

## 10. Database and Schema

- [x] Review `products`, `product_variants`, and `product_media` schema together.
- [x] Review indexes supporting catalog and checkout queries.
- [x] Review rollback safety of recent migrations.
- [ ] Confirm `variant_type` migration has a proper `down()` strategy.
- [ ] Confirm schema supports the chosen long-term variant contract.

Audit notes (2026-04-01, pass 5):
- Core product/variant/media schema supports current storefront use-cases and variant-media linkage.
- Relevant indexes exist for common catalog filters (status/activity/category), but additional composite indexes may be useful for high-scale sorting/filter paths and media ordering workloads.
- Rollback review found a concrete gap: variant_type migration down() is currently empty and does not reverse schema changes.

## 11. Testing and Coverage

- [x] Review current test coverage for variant CRUD.
- [x] Review current test coverage for shop product presentation.
- [x] Review current test coverage for legacy import.
- [x] Review current test coverage for cart and checkout.
- [x] Add tests for typed size variants.
- [x] Add tests for typed color variants.
- [x] Add tests for combo variants.
- [x] Add tests for variant-specific media.
- [x] Add tests for variant import normalization.
- [x] Fix the currently failing preorder presentation test.

Audit notes (2026-04-01, pass 3):
- Variant CRUD coverage exists but is currently create-focused; explicit update/delete variant tests are still missing.
- Shop presentation coverage is strong for variant attribute rendering, price ranges, and preorder messaging.
- Legacy import coverage includes variation attribute normalization into option values and preorder propagation.
- Cart and checkout coverage is strong for core flows and thumbnail path behavior.
- Added explicit storefront tests for typed size, typed color, combo variant presentation, and variant-specific media metadata.

## 11A. Code Quality and Laravel Standards

- [x] Review controllers for excessive business logic.
- [x] Review models for missing scopes, casts, relationship typing, and domain leakage.
- [x] Review requests/forms for validation quality and normalization gaps.
- [x] Review Blade views for business logic that should not live in templates.
- [x] Review JavaScript for dead code, duplicated logic, and brittle DOM contracts.
- [x] Review commands and scripts for production safety and maintainability.
- [x] Review database access for N+1 risk, missing eager loading, and weak indexes.
- [x] Review whether the codebase generally follows Laravel conventions rather than ad hoc patterns.
- [x] Identify places where custom code should be extracted into actions/services.
- [x] Identify places where the code clearly diverges from Laravel best practices.

Audit notes (2026-04-01, pass 4):
- Controllers: Shop and checkout controllers are feature-rich and include substantial transformation and orchestration logic that should be gradually extracted into action/service classes.
- Models: casts and relationship typing are generally good, but there is limited use of query scopes for repeated availability/filter patterns.
- Requests: form requests are used consistently; normalization currently relies mostly on controller/service code rather than request-level normalization.
- Blade: product page templates include heavy @php data shaping and JSON-LD building in-view; this should move to view models/presenters.
- JavaScript: app.js contains multiple large concerns (variant picker, gallery/lightbox, recaptcha), creating brittle DOM contracts and high coupling.
- Commands/scripts: Laravel commands are structured reasonably; operational Python scripts remain fragmented with duplicated connection/deploy logic.
- Query review: major storefront flows are eager-loaded appropriately; index/show/search now avoid obvious N+1 in current audited paths.
- Laravel convention fit: core patterns are mostly Laravel-aligned, but there is ad hoc operational tooling and oversized controller/view responsibilities.
- Extraction candidates: variant selector construction and media path resolution in shop flows, plus checkout context/pricing orchestration.
- Divergence hotspots: extensive Blade business logic, monolithic frontend script, and duplicated deploy/ops scripting.

## 12. Deployment and Staging

- [x] Review staging deploy workflow for catalog-specific risks.
- [ ] Add a post-deploy smoke test for a product with multiple variants.
- [ ] Confirm migrations and caches do not leave stale catalog behavior in staging.
- [ ] Confirm staging is suitable for validating imported WooCommerce products.

## 12A. Git and Delivery Review

- [x] Review recent git history for risky or unstable changes around catalog and variants.
- [x] Identify whether pushes suggest rushed rework, partial refactors, or repeated hotfixing in the same area.
- [x] Confirm deployment workflow matches the way the team is actually shipping changes.
- [x] Identify what should be tested before each push to staging or main.
- [x] Identify whether there are release gates missing for catalog integrity and storefront basics.

Audit notes (2026-04-01, pass 2):
- Critical risk: multiple tracked scripts contain plaintext SSH host/user/password credentials; this should be rotated and removed from versioned files.
- Delivery currently relies on ad hoc Python deploy scripts with overlapping purposes and no single canonical script, increasing operational risk.

Audit notes (2026-04-01, pass 9):
- Recent history shows heavy repeated churn in storefront variant/gallery files (especially shop show and app.js), indicating hotfix loops and partial refactors.
- Delivery in practice is script-driven with multiple deploy entrypoints rather than one canonical pipeline.
- Minimum pre-push staging/main test set should include: ShopBrowseTest, ShopProductPresentationTest, CartFlowTest, CheckoutFlowTest, LegacyImportCommandTest, and ShopPaginationTest.
- Missing release gates include: mandatory secret scan, mandatory targeted test suite gate by changed area, and standardized post-deploy smoke checklist.

Immediate remediation sequence:
1. Rotate staging SSH credentials and invalidate current password-based access.
2. Move deploy script auth to environment variables or key-based auth only.
3. Consolidate to one canonical deploy entrypoint and archive deprecated scripts.
4. Add a pre-commit secret scan and CI gate for known credential patterns.

Security follow-up audit tasks (requested):
- [ ] 1. Rotate staging SSH credentials and invalidate current password-based access.
- [ ] 2. Move deploy script auth to environment variables or key-based auth only across all remaining scripts.
- [ ] 3. Add pre-commit secret scan and CI gate for known credential patterns.

## 13. Deliverables

- [ ] Produce a finalized architecture summary.
- [ ] Produce a finalized variant failure analysis.
- [ ] Produce a storefront readiness summary.
- [ ] Produce an admin coverage gap summary.
- [ ] Produce a git/change-quality summary.
- [ ] Produce a Laravel standards/code-quality summary.
- [ ] Produce a prioritized remediation plan.
- [ ] Separate quick fixes from structural refactors.
- [ ] Define the order of implementation for cleanup work.
