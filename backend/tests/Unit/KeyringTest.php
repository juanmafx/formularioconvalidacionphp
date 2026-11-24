<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Config\ConfigurationException;
use ContactForm\Security\Keyring;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeyringTest extends TestCase
{
    public static function invalidDefinitions(): iterable
    {
        $key = base64_encode(str_repeat('a', 32));
        yield 'missing separator' => ['k1' . $key, 'k1'];
        yield 'short key' => ['k1:' . base64_encode('short'), 'k1'];
        yield 'invalid base64' => ['k1:@@@', 'k1'];
        yield 'uppercase id' => ['K1:' . $key, 'K1'];
        yield 'duplicate id' => ['k1:' . $key . ',k1:' . $key, 'k1'];
        yield 'inactive id' => ['k1:' . $key, 'k2'];
    }

    #[DataProvider('invalidDefinitions')]
    public function testRejectsInvalidDefinitions(string $definition, string $active): void
    {
        $this->expectException(ConfigurationException::class);
        Keyring::parse($definition, $active);
    }

    public function testGeneratedEntryParses(): void
    {
        $keyring = Keyring::parse(Keyring::generateEntry('k9'), 'k9');

        self::assertSame('k9', $keyring->activeKeyId);
    }

    public function testDerivedKeysAreDomainSeparated(): void
    {
        $keyring = Keyring::parse(Keyring::generateEntry('k1'), 'k1');

        self::assertNotSame($keyring->encryptionKey('k1'), $keyring->pseudonymizationKey());
        self::assertSame(32, strlen($keyring->pseudonymizationKey()));
    }
}
