# Tasks: OTP Delivery Resilience

**Input**: Design documents from `specs/003-otp-delivery-resilience/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/api-contract.md`, `quickstart.md`

**Tests**: Required for every behavior change. Focused tests must fail before implementation.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run independently in a different file.
- **[US1]**–**[US3]**: Maps the task to a user story in `spec.md`.

## Phase 1: Setup and Baseline

- [x] T001 Record the clean branch/remotes, current test baseline, and user-owned `STUDY_NOTES.md` exclusion in `specs/003-otp-delivery-resilience/tasks.md`
- [x] T002 Confirm all requirements and resilience checklist items are complete in `specs/003-otp-delivery-resilience/checklists/`

**Baseline evidence (2026-07-22)**: branch `feature/docs`; intended remote `clean` points to
`seaf26/laravel-store-api-clean`. Pre-change suites: SQLite 152 passed / 2 skipped (688
assertions), MySQL 154 passed (705 assertions), PostgreSQL 154 passed (705 assertions).
`STUDY_NOTES.md` is user-owned and remains untracked/excluded. Requirements checklist: 16/16;
resilience checklist: 22/22.

## Phase 2: User Story 1 - Safe OTP Replacement and Delivery Failure (Priority: P1)

**Goal**: Older codes are explicitly superseded; failed deliveries are persisted, unusable, and do not consume the per-phone allowance.

**Independent Test**: Replace and fail OTP deliveries, then inspect lifecycle timestamps, redeemability, endpoint responses, and limiter counters.

### Tests for User Story 1

- [x] T003 [P] [US1] Add OTP supersession and delivered-state assertions in `tests/Feature/Auth/PhoneVerificationTest.php`
- [x] T004 [P] [US1] Add registration delivery-failure response and account-retention tests in `tests/Feature/Auth/RegistrationTest.php`
- [x] T005 [P] [US1] Add verification resend delivery-failure, retry-header, and limiter-refund tests in `tests/Feature/Auth/PhoneVerificationTest.php`
- [x] T006 [P] [US1] Add password-recovery delivery-failure, retry-header, and limiter-refund tests in `tests/Feature/Auth/PasswordResetTest.php`
- [x] T007 [US1] Run the focused auth tests before implementation and record the expected failures in `specs/003-otp-delivery-resilience/tasks.md`

**Red gate (2026-07-22)**: 9 focused tests produced 0 passes, 6 assertion failures, and
3 errors. Existing behavior returned `500` for SMS failures, left supersession metadata
unset, accepted 1,001-character descriptions, lacked the shared classifier, and converted
the simulated non-duplicate order error into a missing-replay lookup.

### Implementation for User Story 1

- [x] T008 [US1] Add reversible lifecycle columns/backfill in `database/migrations/2026_07_22_020000_add_delivery_lifecycle_to_otp_codes_table.php`
- [x] T009 [US1] Add lifecycle fillable/casts/usability semantics in `app/Models/OtpCode.php`
- [x] T010 [US1] Add the typed provider failure in `app/Exceptions/OtpDeliveryFailedException.php`
- [x] T011 [US1] Implement atomic supersession, delivery success/failure persistence, and failed-row quota exclusion in `app/Services/Otp/OtpService.php`
- [x] T012 [US1] Return safe registration/recovery/resend responses and retry guidance in `app/Http/Controllers/Api/AuthController.php`, `app/Http/Controllers/Api/PhoneVerificationController.php`, and `app/Http/Controllers/Api/PasswordResetController.php`
- [x] T013 [US1] Make confirmed provider failures free in the phone bucket while preserving the source ceiling in `app/Providers/AppServiceProvider.php`
- [x] T014 [US1] Run focused auth tests and migration up/down/reapply checks on the supported database families

## Phase 3: User Story 2 - Correct Database Failure Classification (Priority: P1)

**Goal**: Only proven duplicate-key errors become no-ops; every other failure propagates.

**Independent Test**: Classify supported duplicate signatures and representative connection, deadlock, constraint, syntax, and runtime failures.

### Tests for User Story 2

- [x] T015 [P] [US2] Add driver-specific duplicate and non-duplicate classification tests in `tests/Unit/Support/Database/UniqueConstraintViolationDetectorTest.php`
- [x] T016 [P] [US2] Add non-database notification failure propagation coverage in `tests/Feature/Notification/NotificationDeduplicationTest.php`
- [x] T016A [P] [US2] Add non-duplicate order idempotency QueryException propagation coverage in `tests/Feature/Order/OrderIdempotencyTest.php`
- [x] T017 [US2] Run the focused notification tests before implementation and record the expected failures in `specs/003-otp-delivery-resilience/tasks.md`

### Implementation for User Story 2

- [x] T018 [US2] Centralize narrow duplicate-key classification and use it from notification and order idempotency services
- [x] T019 [US2] Run notification unit, listener, and production concurrency regressions on SQLite, MySQL, and PostgreSQL

## Phase 4: User Story 3 - Bounded Request Descriptions (Priority: P2)

**Goal**: Product create/update accept at most 1,000 description characters.

**Independent Test**: Exercise exact 1,000/1,001-character boundaries on both write endpoints.

### Tests for User Story 3

- [x] T020 [P] [US3] Add product create description boundary tests in `tests/Feature/Product/ProductManagementTest.php`
- [x] T021 [P] [US3] Add product update description boundary tests in `tests/Feature/Product/ProductManagementTest.php`
- [x] T022 [US3] Run focused product tests before implementation and record the expected failures in `specs/003-otp-delivery-resilience/tasks.md`

### Implementation for User Story 3

- [x] T023 [P] [US3] Add the 1,000-character create rule in `app/Http/Requests/Product/StoreProductRequest.php`
- [x] T024 [P] [US3] Add the 1,000-character update rule in `app/Http/Requests/Product/UpdateProductRequest.php`
- [x] T025 [US3] Run product management and listing regressions and confirm the standard validation envelope

## Phase 5: Documentation and Cross-Cutting Verification

- [x] T026 [P] Update OTP lifecycle, outage, limiter, query-error, and description contracts in `README.md`
- [x] T027 [P] Update lifecycle/failure diagrams and migration behavior in `docs/reliability.md`
- [x] T028 [P] Add representative `503`, retry-header, and 1,001-character validation examples in `docs/postman_collection.json`
- [x] T029 Run full SQLite, MySQL 8, and PostgreSQL 16 suites plus Pint, PHP syntax, Composer validation/audit, Postman JSON, workflow YAML, scheduler, and `git diff --check`
- [x] T030 Run final convergence, mark every completed task, record exact results in `specs/003-otp-delivery-resilience/tasks.md`, and preserve `STUDY_NOTES.md`

## Completion Evidence (2026-07-22)

- Lifecycle migration up/backfill/down/reapply: passed on SQLite, MySQL 8, and PostgreSQL 16.
- SQLite: 165 tests, 163 passed, 2 engine-specific skips, 806 assertions.
- MySQL 8: 165 passed, 823 assertions; production concurrency tests enabled.
- PostgreSQL 16: 165 passed, 823 assertions; production concurrency tests enabled.
- Pint, PHP syntax, Composer strict validation, locked dependency audit, Postman JSON,
  workflow YAML, scheduler discovery, and `git diff --check`: passed.
- Final requirement/task/checklist convergence: no critical, high, or medium gaps.
- User-owned untracked `STUDY_NOTES.md` remains untouched and excluded.

## Dependencies and Execution Order

- T001–T002 establish traceability and gates.
- US1 is the MVP and owns the migration plus OTP boundary.
- US2 and US3 are independent after setup and may be implemented in either order.
- Documentation and complete verification depend on all three stories.

## Parallel Opportunities

- T003–T006 affect separate auth test files except the coordinated verification cases.
- T015 and T016 affect unit and feature notification suites independently.
- T023 and T024 affect separate Form Requests.
- T026–T028 affect separate documentation artifacts.

## Implementation Strategy

1. Write failing auth lifecycle/outage tests, then implement the migration and service flow.
2. Prove non-duplicate exception propagation without weakening atomic deduplication.
3. Add exact description boundaries through existing Form Requests.
4. Synchronize public docs, validate all database families, and converge before commit/push.
