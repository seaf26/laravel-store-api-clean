<?php

namespace Tests\Unit\Support\Database;

use App\Support\Database\UniqueConstraintViolationDetector;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class UniqueConstraintViolationDetectorTest extends TestCase
{
    public function test_it_recognizes_supported_duplicate_key_signatures(): void
    {
        $detector = new UniqueConstraintViolationDetector;

        $this->assertTrue($detector->causedBy(
            $this->queryException('pgsql', ['23505', 7, 'duplicate key value violates unique constraint']),
            'notifications.id',
        ));
        $this->assertTrue($detector->causedBy(
            $this->queryException('mysql', ['23000', 1062, 'Duplicate entry']),
            'notifications.id',
        ));
        $this->assertTrue($detector->causedBy(
            $this->queryException('sqlite', ['23000', 19, 'UNIQUE constraint failed: notifications.id']),
            'notifications.id',
        ));
        $this->assertTrue($detector->causedBy(
            $this->queryException('sqlite', ['23000', 19, 'UNIQUE constraint failed: idempotency_keys.user_id, idempotency_keys.key']),
            'idempotency_keys.user_id, idempotency_keys.key',
        ));
    }

    public function test_it_rejects_non_duplicate_database_failures(): void
    {
        $detector = new UniqueConstraintViolationDetector;
        $cases = [
            $this->queryException('pgsql', ['08006', 7, 'connection failure']),
            $this->queryException('pgsql', ['23503', 7, 'foreign key violation']),
            $this->queryException('pgsql', ['42601', 7, 'syntax error']),
            $this->queryException('mysql', ['40001', 1213, 'deadlock found']),
            $this->queryException('mysql', ['23000', 1452, 'foreign key constraint fails']),
            $this->queryException('mysql', ['42000', 1064, 'SQL syntax error']),
            $this->queryException('sqlite', ['23000', 19, 'NOT NULL constraint failed: notifications.data']),
            $this->queryException('sqlite', ['23000', 19, 'UNIQUE constraint failed: notifications.notifiable_id']),
        ];

        foreach ($cases as $exception) {
            $this->assertFalse($detector->causedBy($exception, 'notifications.id'));
        }
    }

    /**
     * @param  array{0: string, 1: int, 2: string}  $errorInfo
     */
    private function queryException(string $connection, array $errorInfo): QueryException
    {
        $previous = new PDOException($errorInfo[2]);
        $previous->errorInfo = $errorInfo;

        return new QueryException($connection, 'insert into test values (?)', [], $previous);
    }
}
