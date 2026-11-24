<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Security\DecryptionFailedException;
use ContactForm\Security\FieldCipher;
use ContactForm\Security\Keyring;
use PHPUnit\Framework\TestCase;

final class FieldCipherTest extends TestCase
{
    private static function keyring(string $active = 'k1'): Keyring
    {
        return Keyring::parse('k1:' . base64_encode(str_repeat('a', 32)) . ',k2:' . base64_encode(str_repeat('b', 32)), $active);
    }

    public function testRoundTripsPlaintext(): void
    {
        $cipher = new FieldCipher(self::keyring());

        self::assertSame('sensitive value', $cipher->decrypt($cipher->encrypt('sensitive value', 'ctx'), 'ctx'));
    }

    public function testCiphertextDoesNotContainPlaintext(): void
    {
        $envelope = (new FieldCipher(self::keyring()))->encrypt('sensitive value', 'ctx');

        self::assertStringNotContainsString('sensitive', $envelope);
        self::assertStringNotContainsString(base64_encode('sensitive'), $envelope);
    }

    public function testNoncesAreUnique(): void
    {
        $cipher = new FieldCipher(self::keyring());

        self::assertNotSame($cipher->encrypt('value', 'ctx'), $cipher->encrypt('value', 'ctx'));
    }

    public function testRejectsDifferentContext(): void
    {
        $cipher = new FieldCipher(self::keyring());
        $envelope = $cipher->encrypt('value', 'submission:a');

        $this->expectException(DecryptionFailedException::class);
        $cipher->decrypt($envelope, 'submission:b');
    }

    public function testRejectsTamperedCiphertext(): void
    {
        $cipher = new FieldCipher(self::keyring());
        $envelope = $cipher->encrypt('value', 'ctx');
        $parts = explode('.', $envelope);
        $bytes = sodium_base642bin($parts[2], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $bytes[strlen($bytes) - 1] = $bytes[strlen($bytes) - 1] ^ "\x01";
        $parts[2] = sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        $this->expectException(DecryptionFailedException::class);
        $cipher->decrypt(implode('.', $parts), 'ctx');
    }

    public function testRejectsMalformedEnvelope(): void
    {
        $this->expectException(DecryptionFailedException::class);
        (new FieldCipher(self::keyring()))->decrypt('v1.k1.!!!', 'ctx');
    }

    public function testRejectsUnknownKeyId(): void
    {
        $envelope = (new FieldCipher(self::keyring('k2')))->encrypt('value', 'ctx');
        $other = new FieldCipher(Keyring::parse('k1:' . base64_encode(str_repeat('a', 32)), 'k1'));

        $this->expectException(DecryptionFailedException::class);
        $other->decrypt($envelope, 'ctx');
    }

    public function testDecryptsWithRetiredKeyAfterRotation(): void
    {
        $envelope = (new FieldCipher(self::keyring('k1')))->encrypt('value', 'ctx');

        self::assertSame('value', (new FieldCipher(self::keyring('k2')))->decrypt($envelope, 'ctx'));
    }
}
