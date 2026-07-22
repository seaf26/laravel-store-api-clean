<?php

namespace App\Services\Otp;

/**
 * Request-scoped signal shared by OTP controllers and the named rate limiter.
 */
final class OtpDeliveryAttemptState
{
    private bool $failed = false;

    public function markFailed(): void
    {
        $this->failed = true;
    }

    public function consumeFailure(): bool
    {
        $failed = $this->failed;
        $this->failed = false;

        return $failed;
    }
}
