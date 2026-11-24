<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Security\PasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    private const string PASSWORD = 'a-long-enough-password';

    private static function hasher(int $memory = 19456): PasswordHasher
    {
        return new PasswordHasher($memory, 2, 1);
    }

    public function testProducesArgon2idHash(): void
    {
        self::assertStringStartsWith('$argon2id$', self::hasher()->hash(self::PASSWORD));
    }

    public function testSaltsEachHash(): void
    {
        $hasher = self::hasher();

        self::assertNotSame($hasher->hash(self::PASSWORD), $hasher->hash(self::PASSWORD));
    }

    public function testVerifiesCorrectPasswordOnly(): void
    {
        $hasher = self::hasher();
        $hash = $hasher->hash(self::PASSWORD);

        self::assertTrue($hasher->verify(self::PASSWORD, $hash));
        self::assertFalse($hasher->verify('another-long-password', $hash));
    }

    public function testRejectsShortPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::hasher()->hash('short');
    }

    public function testRejectsOversizedPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::hasher()->hash(str_repeat('a', PasswordHasher::MAX_LENGTH + 1));
    }

    public function testRejectsNullBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::hasher()->hash("a-long-enough\0password");
    }

    public function testVerifyHandlesHostileInput(): void
    {
        $hasher = self::hasher();
        $hash = $hasher->hash(self::PASSWORD);

        self::assertFalse($hasher->verify("with\0null-byte-value", $hash));
        self::assertFalse($hasher->verify(str_repeat('a', 100000), $hash));
    }

    public function testRejectedInputStillPerformsHashingWork(): void
    {
        $hasher = self::hasher();
        $hash = $hasher->hash(self::PASSWORD);
        $startedAt = hrtime(true);
        $hasher->verify('another-long-password', $hash);
        $normalDuration = hrtime(true) - $startedAt;
        $startedAt = hrtime(true);
        $hasher->verify(str_repeat('a', PasswordHasher::MAX_LENGTH + 1), $hash);
        $oversizedDuration = hrtime(true) - $startedAt;
        $startedAt = hrtime(true);
        $hasher->verify("with\0null-byte-value", $hash);
        $nullByteDuration = hrtime(true) - $startedAt;

        self::assertGreaterThan($normalDuration / 4, $oversizedDuration);
        self::assertGreaterThan($normalDuration / 4, $nullByteDuration);
    }

    public function testBurnVerificationAcceptsHostileInput(): void
    {
        self::hasher()->burnVerification("with\0null-byte-value");

        $this->addToAssertionCount(1);
    }

    public function testDetectsOutdatedParameters(): void
    {
        $hash = self::hasher(19456)->hash(self::PASSWORD);

        self::assertTrue(self::hasher(32768)->needsRehash($hash));
        self::assertFalse(self::hasher(19456)->needsRehash($hash));
    }
}
