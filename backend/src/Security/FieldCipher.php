<?php

declare(strict_types=1);

namespace ContactForm\Security;

final readonly class FieldCipher
{
    private const string FORMAT_VERSION = 'v1';

    public function __construct(private Keyring $keyring)
    {
    }

    public function activeKeyId(): string
    {
        return $this->keyring->activeKeyId;
    }

    public function encrypt(string $plaintext, string $context): string
    {
        $keyId = $this->keyring->activeKeyId;
        $key = $this->keyring->encryptionKey($keyId) ?? throw new DecryptionFailedException('Active key unavailable.');
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::associatedData($keyId, $context),
            $nonce,
            $key,
        );
        sodium_memzero($key);

        return implode('.', [
            self::FORMAT_VERSION,
            $keyId,
            sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
        ]);
    }

    public function decrypt(string $envelope, string $context): string
    {
        $parts = explode('.', $envelope);
        if (count($parts) !== 3 || $parts[0] !== self::FORMAT_VERSION) {
            throw new DecryptionFailedException('Malformed ciphertext.');
        }
        [, $keyId, $encoded] = $parts;
        $key = $this->keyring->encryptionKey($keyId) ?? throw new DecryptionFailedException('Unknown key id.');
        try {
            $decoded = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            throw new DecryptionFailedException('Malformed ciphertext.');
        }
        if (strlen($decoded) < SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new DecryptionFailedException('Malformed ciphertext.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            self::associatedData($keyId, $context),
            $nonce,
            $key,
        );
        sodium_memzero($key);
        if ($plaintext === false) {
            throw new DecryptionFailedException('Ciphertext authentication failed.');
        }

        return $plaintext;
    }

    private static function associatedData(string $keyId, string $context): string
    {
        return self::FORMAT_VERSION . '|' . $keyId . '|' . $context;
    }
}
