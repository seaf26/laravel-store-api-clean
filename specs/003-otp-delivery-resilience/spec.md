# Feature Specification: OTP Delivery Resilience

**Feature Branch**: `feature/docs`

**Created**: 2026-07-22

**Status**: Complete

**Input**: User description: "Handle non-duplicate query exceptions, limit every request description to 1,000 characters, make SMS outages safe for OTP users and rate limits, supersede prior OTPs, and persist failed delivery state."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Safe OTP Replacement and Delivery Failure (Priority: P1)

A customer requesting a new verification or password-reset code receives at most one usable
code for that purpose. If the SMS provider cannot deliver the new code, that code is visibly
failed in internal state, cannot be redeemed, and does not consume the customer's successful
issuance allowance.

**Why this priority**: An undelivered active code and repeated attempts that consume the
customer's quota combine a security-state defect with a lockout-prone recovery experience.

**Independent Test**: Request two codes and prove the first becomes expired/superseded; then
force SMS delivery failure and prove the new code is failed, unusable, excluded from the
per-phone successful-delivery quota, and produces the endpoint's documented retry guidance.

**Acceptance Scenarios**:

1. **Given** a usable code for a phone and purpose, **When** a replacement is issued, **Then** the older code is immediately expired, marked superseded, and cannot be redeemed.
2. **Given** SMS delivery succeeds, **When** issuance completes, **Then** the new code is marked delivered and is the only redeemable code for that phone and purpose.
3. **Given** SMS delivery fails, **When** issuance completes, **Then** the new code is marked failed and expired, cannot be redeemed, and does not count toward the successful per-phone issue window.
4. **Given** account registration and an SMS delivery failure, **When** the response is returned, **Then** the customer receives a retryable service-unavailable response with retry guidance and the created account remains available for a later resend.
5. **Given** a public verification-resend or password-recovery request, **When** the phone is unknown, internally limited, or delivery fails, **Then** account existence remains undisclosed through the public body while a delivery failure does not consume the per-phone delivery allowance.

---

### User Story 2 - Correct Database Failure Classification (Priority: P1)

A notification retry or concurrent idempotent order treats only a database duplicate-key
collision as an already-completed claim. Connection failures, deadlocks, foreign-key failures,
malformed queries, and other database errors remain failures so they can retry or surface.

**Why this priority**: Swallowing a non-duplicate database error can silently lose a
notification and falsely report successful deduplication.

**Independent Test**: Exercise recognized duplicate signatures for each supported database
and representative non-duplicate signatures, proving only duplicate delivery/order-key claims
become no-ops or replays.

**Acceptance Scenarios**:

1. **Given** a duplicate notification identity, **When** its insert raises a supported duplicate-key error, **Then** delivery returns a duplicate no-op and does not continue to later channels.
2. **Given** any other query error, **When** notification delivery encounters it, **Then** the original failure propagates unchanged.
3. **Given** a non-database exception, **When** delivery encounters it, **Then** the original failure propagates unchanged.
4. **Given** an idempotency-key insert raises a non-duplicate query error, **When** order placement handles it, **Then** the original database failure propagates and the order transaction rolls back.

---

### User Story 3 - Bounded Request Descriptions (Priority: P2)

An administrator can create or update a product description up to 1,000 characters, while
oversized descriptions are rejected consistently before persistence.

**Why this priority**: A clear boundary prevents unexpectedly large request bodies and keeps
create/update validation behavior consistent.

**Independent Test**: Submit descriptions of exactly 1,000 and 1,001 characters to both
product write endpoints and verify the boundary behavior.

**Acceptance Scenarios**:

1. **Given** an otherwise valid product request with a 1,000-character description, **When** it is submitted, **Then** validation accepts it.
2. **Given** a product create or update request with a 1,001-character description, **When** it is submitted, **Then** it receives a field-specific validation error and no mutation occurs.

### Edge Cases

- The same phone may hold independent usable codes for different purposes; replacement affects only the matching phone/purpose pair.
- A provider can accept an SMS and the subsequent delivery-state update can fail; this database failure must propagate and be observable rather than be mislabeled as provider failure.
- A failed delivery-state write must not hide the original provider exception; both failures must remain observable.
- Superseded and failed records remain available for rate-limit auditing and scheduled pruning.
- Anonymous issue endpoints must not make known and unknown phones distinguishable through success bodies.
- Source/IP ceilings still count failed requests so an outage cannot be used to hammer the provider indefinitely.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST expire and mark all older usable codes as superseded before creating a replacement for the same normalized phone and purpose.
- **FR-002**: The system MUST persist whether a newly created code was delivered or delivery failed.
- **FR-003**: Only delivered, unconsumed, unsuperseded, unfailed, unexpired codes MUST be redeemable.
- **FR-004**: A failed delivery MUST expire the new code immediately and MUST NOT count toward the successful per-phone issuance window.
- **FR-005**: Registration MUST return a retryable service-unavailable response with a `Retry-After` value when code delivery fails, without deleting the newly created account.
- **FR-006**: Public resend and password-recovery success bodies MUST remain account-enumeration resistant; failure tracking and limiter accounting MUST remain internal at those boundaries.
- **FR-007**: Public OTP-issue responses MUST provide retry timing so customers do not immediately repeat a request during provider trouble.
- **FR-008**: Only recognized duplicate-key query errors for the supported databases MAY be treated as successful notification or idempotency-key deduplication.
- **FR-009**: Every other query or runtime exception during notification delivery or idempotent order placement MUST propagate unchanged.
- **FR-010**: Every writable request field named `description` MUST accept at most 1,000 characters and reject longer values with the standard validation envelope.

### Security and Reliability Requirements *(mandatory for trust-boundary or state changes)*

- **SR-001**: OTP plaintext MUST remain confined to the delivery call and MUST never be persisted, logged, or returned by the API.
- **SR-002**: Replacement and new-code creation MUST remain serialized per normalized phone and purpose, with database state changes committed atomically.
- **SR-003**: Provider failure details, credentials, and response bodies MUST NOT be exposed to API clients; clients receive a stable generic message.
- **SR-004**: Failed delivery MUST not consume the per-phone delivery allowance, while the independent source/IP ceiling MUST remain enforced.
- **SR-005**: Existing OTP records MUST migrate safely as delivered historical records, and rollback MUST remove only the newly added lifecycle metadata.
- **SR-006**: Healthy, throttled, and internal-guard responses for public lookup endpoints MUST continue to avoid revealing account existence.

### Key Entities *(include if feature involves data)*

- **OTP Code**: A hashed one-time credential scoped to a phone and purpose, with expiry, delivery, replacement, consumption, and audit timestamps.
- **Delivery Failure**: A provider-side failure that makes its OTP unusable and supplies safe retry guidance without exposing provider internals.
- **Product Description**: Administrator-supplied catalogue text bounded to 1,000 characters at every write boundary.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Across all tested replacement sequences, exactly one code per phone/purpose is usable after a successful issue and zero are usable after a failed issue.
- **SC-002**: Three consecutive provider failures leave the customer's successful per-phone OTP issuance allowance unchanged while the source abuse ceiling remains active.
- **SC-003**: All supported duplicate-key signatures produce the intended no-op/replay result, and 100% of tested non-duplicate notification and order failures propagate.
- **SC-004**: Product create and update accept exactly 1,000 description characters and reject 1,001 characters with a field-specific validation error.
- **SC-005**: Existing OTP, authentication, notification, and three-database test suites remain passing after the lifecycle migration.

## Assumptions

- Provider errors are retryable from the customer's perspective and use a 60-second retry recommendation.
- Failed OTP rows remain stored for audit/rate-limit context until the existing daily pruning policy removes old rows.
- The source/IP limiter continues to count all incoming issue requests; only the per-phone delivery allowance excludes confirmed provider failures.
- Account-enumeration protection takes precedence over exposing a distinct delivery-failure body on public phone-lookup endpoints; registration can be explicit because the account was created by that request.
- The only writable request field currently named `description` is the product description; future request descriptions inherit the same 1,000-character policy.

## Out of Scope

- Automatic failover to a second SMS provider.
- Guaranteeing delivery after a provider has accepted a message.
- Exposing provider response bodies or detailed Twilio errors to customers.
- Changing notification-channel SMS failures, which remain best-effort after the durable database notification.
