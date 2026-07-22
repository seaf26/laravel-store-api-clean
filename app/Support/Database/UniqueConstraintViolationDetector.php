<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;

final class UniqueConstraintViolationDetector
{
    /**
     * Determine whether an insert failed because its expected identity already
     * exists. All unrecognized database failures must remain exceptions.
     */
    public function causedBy(QueryException $exception, string $sqliteConstraint): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        // PostgreSQL unique_violation.
        if ($state === '23505') {
            return true;
        }

        // MySQL/MariaDB duplicate entry.
        if ($state === '23000' && $driverCode === 1062) {
            return true;
        }

        if ($state !== '23000' || $driverCode !== 19) {
            return false;
        }

        // SQLite uses driver code 19 for several constraint families, so the
        // expected unique columns are required to exclude NOT NULL, FK, and
        // unrelated unique failures.
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return str_contains(
            $message,
            'unique constraint failed: '.strtolower($sqliteConstraint),
        );
    }
}
