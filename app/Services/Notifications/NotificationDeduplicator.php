<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Support\Database\UniqueConstraintViolationDetector;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;

class NotificationDeduplicator
{
    public function __construct(
        private readonly UniqueConstraintViolationDetector $uniqueViolations,
    ) {}

    public function sendOnce(User $recipient, Notification $notification, string $logicalKey): bool
    {
        $notification->id = $this->idFor($recipient, $notification, $logicalKey);

        if ($recipient->notifications()->whereKey($notification->id)->exists()) {
            return false;
        }

        try {
            $recipient->notify($notification);

            return true;
        } catch (QueryException $exception) {
            if ($this->uniqueViolations->causedBy($exception, 'notifications.id')) {
                return false;
            }

            throw $exception;
        }
    }

    public function idFor(User $recipient, Notification $notification, string $logicalKey): string
    {
        $hex = substr(hash('sha256', implode('|', [
            $notification::class,
            $recipient->getMorphClass(),
            (string) $recipient->getKey(),
            $logicalKey,
        ])), 0, 32);

        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
