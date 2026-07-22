# Data Model: OTP Delivery Resilience

## OTP Code

Existing fields remain unchanged. The migration adds:

| Field | Type | Existing-row value | Meaning |
| --- | --- | --- | --- |
| `delivered_at` | nullable timestamp | copied from `created_at` | Provider accepted the code for delivery |
| `delivery_failed_at` | nullable timestamp | `null` | Provider delivery threw; code is unusable |
| `superseded_at` | nullable timestamp | `null` | A newer code replaced this phone/purpose code |

### Derived lifecycle

| State | Predicate |
| --- | --- |
| Pending | all three new timestamps are null and the code is not otherwise spent |
| Delivered | `delivered_at` is set and failure/supersession are null |
| Delivery failed | `delivery_failed_at` is set; `expires_at` is no later than failure time |
| Superseded | `superseded_at` is set; `expires_at` is no later than replacement time |
| Consumed | `consumed_at` is set after an atomic successful claim |
| Naturally expired | `expires_at` is not in the future |

### Usability invariant

A code is redeemable only when `delivered_at` is set; `delivery_failed_at`, `superseded_at`,
and `consumed_at` are null; and `expires_at` is in the future.

### Replacement transition

Within the existing phone/purpose lock and one database transaction:

1. Check the successful issue-window allowance, excluding failed delivery rows.
2. Set `superseded_at` and `expires_at` on every earlier active row for that phone/purpose.
3. Insert one hashed pending row.

After commit, provider success sets `delivered_at`. Provider failure sets
`delivery_failed_at` and expires the new row.

### Migration safety

- Additive nullable columns avoid table-data loss.
- Historical rows are treated as delivered by copying `created_at` into `delivered_at`.
- Rollback drops only the three lifecycle columns.
- No plaintext OTP or provider response body is added.

## Request Description

The persisted product column remains text. Request validation accepts strings of 1–1,000
characters for required product descriptions; values above 1,000 are rejected before model
mutation.

## Database Error Classification

No new data is stored. Duplicate classification is a deterministic mapping from SQLSTATE,
driver code, and—only for SQLite—the expected constrained columns (`notifications.id` or the
idempotency key's `user_id,key` pair). Every unrecognized signature remains an error.
