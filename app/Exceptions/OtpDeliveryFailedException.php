<?php

namespace App\Exceptions;

use RuntimeException;

class OtpDeliveryFailedException extends RuntimeException
{
    public const RETRY_AFTER_SECONDS = 60;
}
