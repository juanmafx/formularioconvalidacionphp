<?php

declare(strict_types=1);

namespace ContactForm\Config;

final readonly class AppConfig
{
    private const int MIN_ARGON_MEMORY_KIB = 19456;
    private const int MIN_ARGON_TIME_COST = 2;

    public function __construct(
        public string $databasePath,
        public string $keyring,
        public string $activeKeyId,
        public bool $secureCookies,
        public bool $mailEnabled,
        public string $mailHost,
        public int $mailPort,
        public string $mailUsername,
        public string $mailPassword,
        public string $mailEncryption,
        public string $mailFrom,
        public string $mailTo,
        public bool $mailIncludeContent,
        public int $retentionDays,
        public int $argonMemoryKib,
        public int $argonTimeCost,
        public int $argonThreads,
    ) {
    }

    public static function fromEnvironment(array $environment, string $projectRoot): self
    {
        $read = static fn (string $name, string $default = ''): string => trim($environment[$name] ?? $default);
        $databasePath = $read('DB_PATH', 'var/contact.sqlite');
        if ($databasePath !== ':memory:' && !str_starts_with($databasePath, '/')) {
            $databasePath = $projectRoot . '/' . $databasePath;
        }

        $config = new self(
            databasePath: $databasePath,
            keyring: self::required($read('APP_KEYRING'), 'APP_KEYRING'),
            activeKeyId: self::required($read('APP_ACTIVE_KEY_ID'), 'APP_ACTIVE_KEY_ID'),
            secureCookies: self::boolean($read('APP_SECURE_COOKIES', 'true'), 'APP_SECURE_COOKIES'),
            mailEnabled: self::boolean($read('MAIL_ENABLED', 'false'), 'MAIL_ENABLED'),
            mailHost: $read('MAIL_HOST'),
            mailPort: self::integer($read('MAIL_PORT', '587'), 'MAIL_PORT', 1, 65535),
            mailUsername: $read('MAIL_USERNAME'),
            mailPassword: $environment['MAIL_PASSWORD'] ?? '',
            mailEncryption: $read('MAIL_ENCRYPTION', 'starttls'),
            mailFrom: $read('MAIL_FROM'),
            mailTo: $read('MAIL_TO'),
            mailIncludeContent: self::boolean($read('MAIL_INCLUDE_CONTENT', 'false'), 'MAIL_INCLUDE_CONTENT'),
            retentionDays: self::integer($read('RETENTION_DAYS', '365'), 'RETENTION_DAYS', 1, 3650),
            argonMemoryKib: self::integer($read('ARGON_MEMORY_KIB', '65536'), 'ARGON_MEMORY_KIB', self::MIN_ARGON_MEMORY_KIB, 4194304),
            argonTimeCost: self::integer($read('ARGON_TIME_COST', '3'), 'ARGON_TIME_COST', self::MIN_ARGON_TIME_COST, 32),
            argonThreads: self::integer($read('ARGON_THREADS', '1'), 'ARGON_THREADS', 1, 16),
        );
        $config->assertMailSettings();

        return $config;
    }

    private function assertMailSettings(): void
    {
        if (!$this->mailEnabled) {
            return;
        }
        if (!in_array($this->mailEncryption, ['starttls', 'smtps'], true)) {
            throw new ConfigurationException('MAIL_ENCRYPTION must be starttls or smtps.');
        }
        foreach (['MAIL_HOST' => $this->mailHost, 'MAIL_FROM' => $this->mailFrom, 'MAIL_TO' => $this->mailTo] as $name => $value) {
            self::required($value, $name);
        }
        foreach (['MAIL_FROM' => $this->mailFrom, 'MAIL_TO' => $this->mailTo] as $name => $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new ConfigurationException($name . ' must be a valid email address.');
            }
        }
    }

    private static function required(string $value, string $name): string
    {
        if ($value === '') {
            throw new ConfigurationException($name . ' is required.');
        }

        return $value;
    }

    private static function boolean(string $value, string $name): bool
    {
        return match (strtolower($value)) {
            'true', '1', 'yes' => true,
            'false', '0', 'no' => false,
            default => throw new ConfigurationException($name . ' must be a boolean.'),
        };
    }

    private static function integer(string $value, string $name, int $minimum, int $maximum): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if ($parsed === false) {
            throw new ConfigurationException(sprintf('%s must be an integer between %d and %d.', $name, $minimum, $maximum));
        }

        return $parsed;
    }
}
