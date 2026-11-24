<?php

declare(strict_types=1);

namespace ContactForm\Console;

use ContactForm\Admin\AdminAuthenticator;
use ContactForm\Admin\RecoveryCodes;
use ContactForm\Application;
use ContactForm\Security\Keyring;
use InvalidArgumentException;
use Throwable;

final readonly class Console
{
    private const string ISSUER = 'Contact Form';
    private const int SECONDS_PER_DAY = 86400;
    private const int RATE_LIMIT_RETENTION_SECONDS = 86400;

    public function __construct(
        private \Closure $applicationFactory,
        private mixed $input,
        private mixed $output,
    ) {
    }

    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? '';
        try {
            return match ($command) {
                'keys:generate' => $this->generateKey((string) ($arguments[2] ?? 'k1')),
                'db:migrate' => $this->migrate(),
                'admin:create' => $this->createAdmin((string) ($arguments[2] ?? '')),
                'maintenance:purge' => $this->purge(),
                default => $this->usage(),
            };
        } catch (Throwable $exception) {
            $safeMessage = $exception instanceof InvalidArgumentException || $exception instanceof \ContactForm\Config\ConfigurationException
                ? $exception->getMessage()
                : 'Command failed: ' . $exception::class;
            $this->line($safeMessage);

            return 1;
        }
    }

    private function generateKey(string $keyId): int
    {
        $this->line(Keyring::generateEntry($keyId));

        return 0;
    }

    private function migrate(): int
    {
        $this->application()->migrate();
        $this->line('Schema applied.');

        return 0;
    }

    private function createAdmin(string $username): int
    {
        $normalizedUsername = strtolower(trim($username));
        if (preg_match('/\A[a-z0-9._-]{3,64}\z/', $normalizedUsername) !== 1) {
            throw new InvalidArgumentException('Usage: admin:create <username> with 3-64 characters from a-z, 0-9, dot, underscore, hyphen. The password is read from standard input.');
        }
        $password = rtrim((string) fgets($this->input), "\r\n");
        $application = $this->application();
        $passwordHash = $application->passwords->hash($password);
        $secret = $application->totp->generateSecret();
        $recoveryCodes = RecoveryCodes::generate();
        $application->adminUsers->create(
            $normalizedUsername,
            $passwordHash,
            $application->cipher->encrypt($secret, AdminAuthenticator::totpContext($normalizedUsername)),
            array_map(RecoveryCodes::hash(...), $recoveryCodes),
        );
        $this->line('Admin created. The values below are shown once.');
        $this->line('TOTP URI: ' . $application->totp->provisioningUri($secret, $normalizedUsername, self::ISSUER));
        $this->line('Recovery codes:');
        foreach ($recoveryCodes as $code) {
            $this->line('  ' . $code);
        }

        return 0;
    }

    private function purge(): int
    {
        $application = $this->application();
        $now = time();
        $cutoff = $now - $application->config->retentionDays * self::SECONDS_PER_DAY;
        $submissions = $application->submissions->purgeOlderThan($cutoff);
        $audit = $application->audit->purgeOlderThan($cutoff);
        $buckets = $application->rateLimiter->purgeOlderThan($now - self::RATE_LIMIT_RETENTION_SECONDS);
        $this->line(sprintf('Purged %d submissions, %d audit events, %d rate limit buckets.', $submissions, $audit, $buckets));

        return 0;
    }

    private function usage(): int
    {
        $this->line('Commands: keys:generate [id] | db:migrate | admin:create <username> | maintenance:purge');

        return 2;
    }

    private function application(): Application
    {
        return ($this->applicationFactory)();
    }

    private function line(string $text): void
    {
        fwrite($this->output, $text . PHP_EOL);
    }
}
