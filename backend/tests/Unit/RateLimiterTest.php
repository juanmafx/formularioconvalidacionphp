<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Persistence\Database;
use ContactForm\Security\RateLimiter;
use ContactForm\Tests\Support\FixedClock;
use ContactForm\Tests\Support\TestHarness;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('clientNetworks')]
    public function testGroupsClientsByNetwork(string $address, string $expected): void
    {
        self::assertSame($expected, RateLimiter::clientNetwork($address));
    }

    public static function clientNetworks(): array
    {
        return [
            'ipv4 unchanged' => ['203.0.113.9', '203.0.113.9'],
            'ipv4-mapped ipv6 uses ipv4' => ['::ffff:203.0.113.9', '203.0.113.9'],
            'ipv6 keeps network prefix' => ['2001:db8:1:2:aaaa:bbbb:cccc:dddd', '20010db800010002::/64'],
            'ipv6 same prefix' => ['2001:db8:1:2::1', '20010db800010002::/64'],
            'ipv6 other prefix' => ['2001:db8:1:3::1', '20010db800010003::/64'],
            'non-address unchanged' => ['not-an-address', 'not-an-address'],
        ];
    }

    public function testPurgesExpiredBuckets(): void
    {
        $this->limiter->allow('scope', 'id', 1, 60);

        self::assertSame(1, $this->limiter->purgeOlderThan($this->clock->now() + 1));
    }
}
