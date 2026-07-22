# Quickstart: OTP Delivery Resilience

## Focused acceptance

```bash
php artisan test tests/Feature/Auth/RegistrationTest.php \
  tests/Feature/Auth/PhoneVerificationTest.php \
  tests/Feature/Auth/PasswordResetTest.php

php artisan test tests/Unit/Services/Notifications/NotificationDeduplicatorTest.php \
  tests/Unit/Support/Database/UniqueConstraintViolationDetectorTest.php \
  tests/Feature/Notification/NotificationDeduplicationTest.php \
  tests/Feature/Order/OrderIdempotencyTest.php

php artisan test tests/Feature/Product/ProductManagementTest.php
```

Expected outcomes:

- replacement leaves exactly one usable delivered OTP;
- provider failure persists an unusable failed row and does not consume the phone allowance;
- registration receives generic `503` plus `Retry-After: 60` when delivery fails;
- public recovery/resend responses remain enumeration-safe and include retry guidance;
- recognized duplicate-key failures are no-ops while all other failures propagate;
- product descriptions accept 1,000 and reject 1,001 characters.

## Migration acceptance

Apply the migration to a database containing OTP history, verify historical rows receive
`delivered_at`, roll back only the new lifecycle columns, and reapply. Repeat migration and
focused tests on SQLite, MySQL 8, and PostgreSQL 16.

## Full quality gate

```bash
vendor/bin/pint --test
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
php artisan test
php artisan schedule:list
jq empty docs/postman_collection.json
git diff --check
```

The production-database jobs must execute the complete test suite without concurrency skips.
