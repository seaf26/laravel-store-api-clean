# API Contract: OTP Delivery Resilience

Unlisted authentication, authorization, validation envelopes, and success resource bodies
remain unchanged.

## Registration delivery failure

When account creation succeeds but verification-code delivery fails:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: application/json
Retry-After: 60

{
  "message": "Account created, but the verification code could not be delivered. Please request a new code later."
}
```

The account remains created and unverified. Provider details are not returned.

## Verification resend and password recovery

The existing generic `200` bodies remain stable to avoid account enumeration. Successful,
unknown-phone, internally limited, and provider-failure paths do not expose provider details.
All successful public issue responses include:

```http
Retry-After: 60
```

Clients should wait at least that many seconds before retrying when no SMS arrives. A confirmed
provider failure does not consume the phone-specific successful-delivery allowance; the source
ceiling remains applicable.

## Description validation

`POST /api/products` and `PUT|PATCH /api/products/{product}` accept `description` values up
to 1,000 characters. Longer input receives the standard `422` validation envelope:

```json
{
  "message": "The description field must not be greater than 1000 characters.",
  "errors": {
    "description": ["The description field must not be greater than 1000 characters."]
  }
}
```

## Duplicate-key database failures

There is no new public response. A duplicate notification identity remains a no-op and a
duplicate order idempotency key follows the existing replay/conflict contract. Any database
failure that is not a recognized duplicate-key violation propagates so normal exception and
retry handling applies.
