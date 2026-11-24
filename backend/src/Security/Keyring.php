<?php

declare(strict_types=1);

namespace ContactForm\Security;

use ContactForm\Config\ConfigurationException;

final readonly class Keyring
{
    private const string KEY_ID_PATTERN = '/\A[a-z0-9]{1,16}\z/';

    private function __construct(
        private array $keys,
        public string $activeKeyId,
    ) {
    }

    public static function parse(string $definition, string $activeKeyId): self
    {
        $keys = [];
        foreach (explode(',', $definition) as $entry) {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) !== 2 || preg_match(self::KEY_ID_PATTERN, $parts[0]) !== 1) {
                throw new ConfigurationException('APP_KEYRING entries must use the form id:base64key.');
            }
            $key = base64_decode($parts[1], true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new ConfigurationException('APP_KEYRING keys must be 32 bytes encoded as base64.');
            }
            if (isset($keys[$parts[0]])) {
                throw new ConfigurationException('APP_KEYRING contains a duplicate key id.');
            }
            $keys[$parts[0]] = $key;
        }
        if (!isset($keys[$activeKeyId])) {
            throw new ConfigurationException('APP_ACTIVE_KEY_ID is not present in APP_KEYRING.');
        }

        return new self($keys, $activeKeyId);
    }

    public static function generateEntry(string $keyId): string
    {
        if (preg_match(self::KEY_ID_PATTERN, $keyId) !== 1) {
            throw new ConfigurationException('Key ids must be 1-16 lowercase alphanumeric characters.');
        }

        return $keyId . ':' . base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function encryptionKey(string $keyId): ?string
    {
        $master = $this->keys[$keyId] ?? null;

        return $master === null ? null : hash_hkdf('sha256', $master, 32, 'contact-form/field-encryption/v1');
    }

    public function pseudonymizationKey(): string
    {
        return hash_hkdf('sha256', $this->keys[$this->activeKeyId], 32, 'contact-form/pseudonymization/v1');
    }
}
