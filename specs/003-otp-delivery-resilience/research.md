# Research: OTP Delivery Resilience

## Decision: Persist lifecycle timestamps instead of one overloaded status string

**Decision**: Add `delivered_at`, `delivery_failed_at`, and `superseded_at` while retaining
the existing expiry and consumption timestamps.

**Rationale**: Independent timestamps preserve audit information and allow unambiguous
queries for delivered, failed, replaced, consumed, and naturally expired states without
state-transition overwrites.

**Alternatives considered**: A single status enum loses timing detail and requires periodic
updates for natural expiry. Reusing `consumed_at` for replacements/failures cannot distinguish
why a code became unusable.

## Decision: Keep provider delivery outside the database transaction

**Decision**: Commit hashed OTP state first, perform SMS delivery outside the transaction and
cache lock, then persist delivered or failed state.

**Rationale**: A remote provider call must not hold a database transaction or distributed
lock. A pending row provides a durable identity that can be marked failed if delivery throws.

**Alternatives considered**: Sending inside the transaction risks long locks and cannot roll
back an SMS. A queue/outbox is larger than this synchronous API's requested scope and would
change the immediate delivery contract.

## Decision: Failed delivery does not charge the per-phone allowance

**Decision**: Exclude failed delivery rows from the database issue window and use the named
limiter's post-response callback plus a request-scoped delivery-attempt signal to avoid hitting
the per-phone HTTP bucket after confirmed provider failure. Continue charging the source/IP
bucket.

**Rationale**: Customers are not locked out for a provider outage, while attackers cannot use
failures to create an unlimited request stream from one source.

**Alternatives considered**: Clearing both buckets enables provider hammering. Counting all
failures recreates the reported user lockout. Removing HTTP throttling violates the security
boundary.

## Decision: Preserve enumeration-safe recovery responses

**Decision**: Registration can report a generic service-unavailable result because that request
created the account. Public verification-resend and forgot-password endpoints keep their
indistinguishable bodies and expose only universal retry guidance; provider details remain
internal.

**Rationale**: A distinct status/body only for existing phones directly reveals account
existence. The constitution makes that privacy boundary mandatory.

**Alternatives considered**: Returning `503` only for known phones improves immediate feedback
but creates an enumeration oracle. Sending probe messages for unknown phones wastes money and
enables SMS abuse.

## Decision: Treat only proven duplicate-key signatures as deduplication

**Decision**: Centralize driver-specific duplicate detection: PostgreSQL SQLSTATE `23505`,
MySQL SQLSTATE `23000` with driver code `1062`, and SQLite constraint code `19` with the
exact expected notification or idempotency-key columns. Use it in both notification delivery
and order idempotency. Propagate all other errors unchanged.

**Rationale**: Queue retry and observability depend on not swallowing connection, deadlock,
foreign-key, syntax, or runtime failures.

**Alternatives considered**: Treating all integrity errors as duplicates hides real defects.
Checking exception message alone is brittle across database drivers.

## Decision: One universal description boundary

**Decision**: Apply `max:1000` to every request rule currently accepting a writable field named
`description` and document that future description inputs inherit the policy.

**Rationale**: The current surface is product create/update. A shared numeric boundary keeps the
public contract predictable without adding a premature custom validation abstraction.

**Alternatives considered**: Database-only truncation silently loses input. A global request
middleware would be harder to understand than the two existing Form Request rules.
