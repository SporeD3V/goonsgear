# GoonsGear Audit Checklist

Date: 2026-04-01

Use this file as the working checklist for the deep app review and remediation effort.

## 1. Foundation and Architecture

- [ ] Confirm the authoritative domain model for products, variants, media, cart, and checkout.
- [ ] Document where business logic currently lives: models, controllers, actions, observers, commands, views, and JS.
- [ ] Identify code paths that should move into dedicated action/service classes.
- [ ] Confirm route, middleware, and admin authorization boundaries.
- [ ] Verify current Laravel 12 conventions are followed consistently.

## 2. Product Variant Domain

- [ ] Define the single source of truth for variant structure.
- [ ] Decide whether `variant_type` is authoritative or should be removed from the business contract.
- [ ] Define the exact schema for `option_values`.
- [ ] Identify all places where variant meaning is inferred from `name`.
- [ ] List all variant states the app must support: simple, size-only, color-only, combo, preorder, backorder.

## 3. Variant Data Flow

- [ ] Trace variant creation from admin form to database.
- [ ] Trace variant update flow from admin form to database.
- [ ] Trace variant display flow on the product page.
- [ ] Trace variant selection flow into cart.
- [ ] Trace variant validation flow into checkout.
- [ ] Trace stock decrement and post-purchase side effects.
- [ ] Trace stock alert behavior for out-of-stock and back-in-stock cases.

## 4. Admin Variant Authoring

- [ ] Review `ProductVariantController` for validation and normalization gaps.
- [ ] Review `StoreProductVariantRequest` and `UpdateProductVariantRequest` for schema-level validation gaps.
- [ ] Review admin variant Blade forms for missing fields and weak UX.
- [ ] Confirm whether admins can reliably create structured variants without raw JSON guesswork.
- [ ] Confirm whether variant-specific media attachment is intuitive and safe.

## 5. Storefront Variant UX

- [ ] Verify the live storefront variant picker implementation.
- [ ] Identify dead or partially integrated variant UI code.
- [ ] Confirm whether size/color/combo selection is actually supported end to end.
- [ ] Confirm gallery filtering follows the selected variant correctly.
- [ ] Confirm cart submission always uses the intended selected variant ID.
- [ ] Confirm preorder messaging renders correctly for selected variants.

## 5A. Storefront Basics

- [ ] Confirm the shop index page works correctly for basic browsing.
- [ ] Confirm product detail pages work correctly for simple products.
- [ ] Confirm product detail pages work correctly for products with multiple variants.
- [ ] Confirm category browsing works correctly.
- [ ] Confirm search returns relevant active products only.
- [ ] Confirm pagination works on shop and category pages.
- [ ] Confirm out-of-stock behavior is correct on listing and detail pages.
- [ ] Confirm cart add/update/remove works for normal shopper flows.
- [ ] Confirm checkout works for a standard order end to end.
- [ ] Confirm legal/basic storefront essentials are present where required: contact, account, cart, checkout success, media delivery.
- [ ] Confirm core SEO basics are present: title, description, canonical, product metadata.
- [ ] Confirm there are no obvious blockers to a shopper completing a basic purchase.

## 6. Media and Variant Media

- [ ] Review product-level versus variant-level media rules.
- [ ] Confirm storefront uses variant media where appropriate.
- [ ] Confirm cart previews use the correct image source.
- [ ] Review media primary-selection rules.
- [ ] Review media deletion logic for orphaned derivatives and fallback originals.
- [ ] Review responsive image conversion and storage behavior.

## 6A. Store Readiness Gaps

- [ ] Identify what the storefront still lacks to function properly at a basic ecommerce level.
- [ ] Identify any missing product information required for a customer to buy confidently.
- [ ] Identify any missing admin controls required to maintain catalog quality.
- [ ] Identify any missing operational controls required to keep the store healthy after launch.
- [ ] Separate true launch blockers from improvements.

## 7. WooCommerce Import

- [ ] Review legacy product import behavior.
- [ ] Review legacy variant import behavior.
- [ ] Confirm imported variants preserve meaningful attribute semantics.
- [ ] Confirm mapping tables are sufficient for safe reruns.
- [ ] Confirm simple product default variant logic is reliable.
- [ ] Confirm preorder data import is correct.
- [ ] Confirm order import maps variants correctly.

## 8. Variant Repair and Maintenance Commands

- [ ] Fix the broken `AssignVariantTypes` command.
- [ ] Decide whether that command should remain or be replaced.
- [ ] Add tests for any variant repair/backfill command.
- [ ] Add a reporting command for ambiguous variant data.
- [ ] Add a data integrity check for missing or malformed variant semantics.

## 8A. Admin Panel Coverage

- [ ] List all important store capabilities that should be manageable from the admin panel.
- [ ] Confirm whether products, variants, media, categories, tags, coupons, discounts, orders, redirects, and integrations are adequately covered.
- [ ] Identify what catalog-maintenance tasks still require code, scripts, or direct database access.
- [ ] Identify what storefront settings are missing from admin even though they should be routine operations.
- [ ] Identify what import/repair/media tasks are too operationally fragile for non-developer use.
- [ ] Identify where admin UX is too raw for safe day-to-day store management.

## 9. Cart and Checkout Integrity

- [ ] Confirm cart only accepts active variants from active products.
- [ ] Confirm quantity limits reflect real inventory behavior.
- [ ] Review checkout stock validation for concurrency risk.
- [ ] Decide whether row locking is needed during order creation.
- [ ] Confirm order items preserve enough historical variant data.
- [ ] Confirm preorder and backorder behavior is consistent between product page, cart, and checkout.

## 10. Database and Schema

- [ ] Review `products`, `product_variants`, and `product_media` schema together.
- [ ] Review indexes supporting catalog and checkout queries.
- [ ] Review rollback safety of recent migrations.
- [ ] Confirm `variant_type` migration has a proper `down()` strategy.
- [ ] Confirm schema supports the chosen long-term variant contract.

## 11. Testing and Coverage

- [ ] Review current test coverage for variant CRUD.
- [ ] Review current test coverage for shop product presentation.
- [ ] Review current test coverage for legacy import.
- [ ] Review current test coverage for cart and checkout.
- [ ] Add tests for typed size variants.
- [ ] Add tests for typed color variants.
- [ ] Add tests for combo variants.
- [ ] Add tests for variant-specific media.
- [ ] Add tests for variant import normalization.
- [ ] Fix the currently failing preorder presentation test.

## 11A. Code Quality and Laravel Standards

- [ ] Review controllers for excessive business logic.
- [ ] Review models for missing scopes, casts, relationship typing, and domain leakage.
- [ ] Review requests/forms for validation quality and normalization gaps.
- [ ] Review Blade views for business logic that should not live in templates.
- [ ] Review JavaScript for dead code, duplicated logic, and brittle DOM contracts.
- [ ] Review commands and scripts for production safety and maintainability.
- [ ] Review database access for N+1 risk, missing eager loading, and weak indexes.
- [ ] Review whether the codebase generally follows Laravel conventions rather than ad hoc patterns.
- [ ] Identify places where custom code should be extracted into actions/services.
- [ ] Identify places where the code clearly diverges from Laravel best practices.

## 12. Deployment and Staging

- [ ] Review staging deploy workflow for catalog-specific risks.
- [ ] Add a post-deploy smoke test for a product with multiple variants.
- [ ] Confirm migrations and caches do not leave stale catalog behavior in staging.
- [ ] Confirm staging is suitable for validating imported WooCommerce products.

## 12A. Git and Delivery Review

- [ ] Review recent git history for risky or unstable changes around catalog and variants.
- [ ] Identify whether pushes suggest rushed rework, partial refactors, or repeated hotfixing in the same area.
- [ ] Confirm deployment workflow matches the way the team is actually shipping changes.
- [ ] Identify what should be tested before each push to staging or main.
- [ ] Identify whether there are release gates missing for catalog integrity and storefront basics.

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
