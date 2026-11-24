<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Persistence\Database;
use ContactForm\Security\RateLimiter;
use ContactForm\Tests\Support\FixedClock;
use ContactForm\Tests\Support\TestHarness;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private PDO $database;
    private FixedClock $clock;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->database = Database::connect(':memory:');
        Database::migrate($this->database, TestHarness::projectRoot() . '/db/schema.sql');
        $this->clock = new FixedClock();
        $this->limiter = new RateLimiter($this->database, $this->clock, str_repeat('k', 32));
    }

    public function testBlocksAfterLimit(): void
    {
        self::assertTrue($this->limiter->allow('scope', 'id', 2, 60));
        self::assertTrue($this->limiter->allow('scope', 'id', 2, 60));
        self::assertFalse($this->limiter->allow('scope', 'id', 2, 60));
    }

    public function testResetsAfterWindow(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);
        self::assertFalse($this->limiter->allow('scope', 'id', 1, 60));
        $this->clock->advance(60);

        self::assertTrue($this->limiter->allow('scope', 'id', 1, 60));
    }

    public function testIsolatesScopesAndIdentifiers(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);

        self::assertTrue($this->limiter->allow('other', 'id', 1, 60));
        self::assertTrue($this->limiter->allow('scope', 'other', 1, 60));
    }

    public function testStoresOnlyKeyedDigests(): void
    {
        $this->limiter->allow('scope', '203.0.113.9', 1, 60);
        $statement = $this->database->query('SELECT bucket_key FROM rate_limit_buckets');
        self::assertNotFalse($statement);
        $key = (string) $statement->fetchColumn();

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $key);
        self::assertStringNotContainsString('203.0.113.9', $key);
    }

    public function testPurgesExpiredBuckets(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);

        self::assertSame(1, $this->limiter->purgeOlderThan($this->clock->now() + 1));
    }
}
