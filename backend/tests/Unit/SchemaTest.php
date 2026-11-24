<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Persistence\Database;
use ContactForm\Tests\Support\TestHarness;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = Database::connect(':memory:');
        Database::migrate($this->database, TestHarness::projectRoot() . '/db/schema.sql');
    }

    public function testCreatesAllTables(): void
    {
        $statement = $this->database->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        self::assertNotFalse($statement);

        self::assertSame(
            ['admin_recovery_codes', 'admin_users', 'audit_events', 'rate_limit_buckets', 'submissions'],
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testMigrationIsIdempotent(): void
    {
        Database::migrate($this->database, TestHarness::projectRoot() . '/db/schema.sql');

        $this->addToAssertionCount(1);
    }

    public function testRejectsNonArgon2idHash(): void
    {
        $this->expectException(PDOException::class);
        $this->database->exec("INSERT INTO admin_users (username, password_hash, totp_secret_encrypted, created_at, updated_at) VALUES ('admin', 'plaintext', 'x', 1, 1)");
    }

    public function testRejectsUnknownNotificationStatus(): void
    {
        $id = str_repeat('a', 32);
        $this->expectException(PDOException::class);
        $this->database->exec("INSERT INTO submissions (id, payload_encrypted, key_id, notification_status, created_at) VALUES ('{$id}', 'x', 'k1', 'bogus', 1)");
    }
}
