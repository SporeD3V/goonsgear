# Audit Remediation Tracker

Date: 2026-03-29

## Critical

- [x] Harden all FormRequest authorization checks
- [x] Protect PayPal checkout capture against session tampering

## High

- [x] Add rate limiting to password reset endpoints
- [x] Add rate limiting to admin destructive maintenance actions
- [x] Fix coupon edit assigned-user loading inefficiency
- [x] Scope nested product variant routes to parent product
- [x] Replace inline cart validation with Form Requests
- [x] Replace inline checkout payload validation with Form Request

## Medium

- [x] Improve account page query bounds to avoid unbounded lists
- [x] Stop exposing stored secrets directly in integration settings form
- [x] Normalize maintenance logs to info level for normal operations

## Validation

- [x] Run Pint formatting
- [x] Run focused tests

## Staging Incidents

- [x] Prevent cart/account 500s when `coupon_user` pivot table is missing on staging
- [ ] Apply pending staging migration `2026_03_29_155259_create_coupon_user_table`
- [ ] Verify staging `migrations` table includes `2026_03_29_155258_add_rule_columns_to_coupons_table`
- [ ] Verify staging `migrations` table includes `2026_03_29_155300_create_order_coupon_usages_table`
- [ ] Remove temporary “personal coupons unavailable while assignments are being updated” fallback after staging schema is aligned

## Deeper Refactors (Phase 2)

- [x] Introduce policy classes for user-owned storefront resources (tag follows, stock alerts)
- [x] Extract checkout order write flow into dedicated action class
- [x] Introduce centralized pagination config keys for admin/storefront listing pages
- [x] Convert account tag lists from hard limits to paginated UI
- [x] Add policy coverage tests for explicit deny/allow paths

## Pending Audits (Phase 3)

- [ ] Migration gate audit (CI/CD must fail when `php artisan migrate:status --no-interaction` reports pending migrations)
- [ ] Route security audit (auth + throttle + policy coverage for all sensitive POST/PATCH/DELETE routes)
- [ ] Query plan audit (run `EXPLAIN` on admin index pages and storefront filters/search)
- [ ] Static analysis audit (Larastan/PHPStan baseline and strict pass)
- [ ] Backup/restore resilience audit (staging restore drill and documented RTO/RPO)

## Migration Pipeline Follow-up

- [ ] Staging shell access confirmed and tested
- [ ] Add pre-deploy migration status check to pipeline
- [ ] Add deploy block when pending migrations are detected
- [ ] Add post-deploy migration verification step
- [ ] Add rollback playbook for failed migrations
