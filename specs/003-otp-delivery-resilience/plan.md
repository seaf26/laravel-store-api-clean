# Implementation Plan: OTP Delivery Resilience

**Branch**: `feature/docs` | **Date**: 2026-07-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-otp-delivery-resilience/spec.md`

## Summary

Extend OTP rows with delivery, failure, and supersession timestamps; serialize replacement
inside the existing phone/purpose lock and transaction; mark provider failures unusable;
exclude confirmed failures from the successful per-phone issuance allowance while retaining
the source ceiling; and preserve enumeration-safe public lookup responses. Add the missing
1,000-character product-description validation boundary and centralize precise database
duplicate classification for notification and order idempotency paths.

## Technical Context

**Language/Version**: PHP 8.3+, Laravel 13

**Primary Dependencies**: Laravel validation, cache locks, Eloquent transactions, named rate limiters, HTTP client, PHPUnit 12

**Storage**: SQLite for fast tests; MySQL 8 and PostgreSQL 16 production-compatible schemas

**Testing**: Laravel feature tests, PHPUnit unit tests, Mockery, HTTP fakes, production-database compatibility suites

**Target Platform**: REST API on Linux-compatible PHP runtime

**Project Type**: Single Laravel web service

**Performance Goals**: OTP replacement remains one transaction plus one provider call; no unbounded retry loop or per-request polling

**Constraints**: Never persist plaintext OTPs; preserve account-enumeration protection; keep source throttling during outages; migration must be additive and reversible

**Scale/Scope**: Three OTP-issue endpoints, two product write requests, one notification deduplicator, and focused documentation/examples

## Constitution Check

*GATE: Passed before research and re-checked after design.*

- [x] Public trust boundaries, rate limits, authorization, and negative paths are identified.
- [x] State invariants and concurrency controls are explicit and database-backed.
- [x] Behavior changes have focused test coverage, including relevant failure and retry paths.
- [x] Public API contracts and documentation changes are identified.
- [x] The design uses the smallest Laravel-native solution; complexity exceptions are recorded.
- [x] Migration, production-database verification, and rollback requirements are addressed.

## Project Structure

### Documentation (this feature)

```text
specs/003-otp-delivery-resilience/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/api-contract.md
├── checklists/requirements.md
├── checklists/resilience.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Exceptions/OtpDeliveryFailedException.php
├── Http/Controllers/Api/{AuthController,PhoneVerificationController,PasswordResetController}.php
├── Http/Requests/Product/{StoreProductRequest,UpdateProductRequest}.php
├── Models/OtpCode.php
├── Providers/AppServiceProvider.php
├── Services/Notifications/NotificationDeduplicator.php
├── Services/Orders/OrderService.php
├── Support/Database/UniqueConstraintViolationDetector.php
└── Services/Otp/OtpService.php
database/migrations/2026_07_22_020000_add_delivery_lifecycle_to_otp_codes_table.php
tests/
├── Feature/Auth/{RegistrationTest,PhoneVerificationTest,PasswordResetTest}.php
├── Feature/Product/ProductManagementTest.php
└── Unit/Support/Database/UniqueConstraintViolationDetectorTest.php
README.md
docs/{reliability.md,postman_collection.json}
```

**Structure Decision**: Keep the existing Laravel controllers, Form Requests, service, and
model boundaries. The OTP service continues to own issuance state and the external delivery
side effect; no repository, outbox, or provider abstraction beyond the current `SmsSender`
contract is added.

## Phase 0: Research Decisions

See [research.md](research.md). Key decisions are additive lifecycle timestamps, explicit
provider-failure state, response-aware per-phone limiting, and precise database-error
classification with non-duplicate propagation.

## Phase 1: Design

- Add nullable `delivered_at`, `delivery_failed_at`, and `superseded_at` timestamps and
  backfill historical rows as delivered.
- A replacement atomically sets both `superseded_at` and `expires_at` on earlier usable rows.
- A new row starts pending; provider success sets `delivered_at`; provider failure sets
  `delivery_failed_at` and expires it before a safe controller response is returned.
- Verification requires delivered, not failed, not superseded, not consumed, and unexpired.
- The named phone issue limiter uses its response callback/request state to avoid charging a
  confirmed provider failure; the independent source limit remains unconditional.
- Registration returns a generic `503` with `Retry-After: 60`. Enumeration-sensitive resend
  and recovery endpoints preserve their existing generic body and add retry guidance without
  exposing provider details.
- Product create/update use the same `max:1000` description rule.
- Notification and order idempotency share a narrow duplicate-key classifier with direct
  regression proof for MySQL, PostgreSQL, SQLite, connection, deadlock, constraint, and
  runtime failures.

## Verification Strategy

1. Run focused tests before implementation and record the expected failures.
2. Run OTP/auth, notification deduplication, and product-management groups after each slice.
3. Exercise migration up/down/reapply with historical OTP records.
4. Run full SQLite, MySQL 8, and PostgreSQL 16 suites.
5. Run Pint, PHP syntax, Composer strict validation/audit, Postman JSON, workflow YAML,
   schedule discovery, and `git diff --check`.

## Complexity Tracking

No constitution violations or additional architectural layers are required.
