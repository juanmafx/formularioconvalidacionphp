<?php

declare(strict_types=1);

namespace ContactForm\Security;

use ContactForm\Support\Clock;
use InvalidArgumentException;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

final readonly class TotpVerifier
{
    public const int PERIOD_SECONDS = 30;
    private const int DIGITS = 6;
    private const int DRIFT_STEPS = 1;
    private const int SECRET_BYTES = 20;

    public function __construct(private Clock $clock)
    {
    }

    public function generateSecret(): string
    {
        return TOTP::generate(null, self::SECRET_BYTES)->getSecret();
    }

    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        if ($accountName === '' || $issuer === '') {
            throw new InvalidArgumentException('Account name and issuer are required.');
        }
        $totp = $this->build($secret);
        $totp->setLabel($accountName);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    public function matchingStep(string $secret, string $code, int $lastAcceptedStep): ?int
    {
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1) {
            return null;
        }
        $totp = $this->build($secret);
        $now = $this->clock->now();
        $currentStep = intdiv($now, self::PERIOD_SECONDS);
        $matched = null;
        for ($step = max(0, $currentStep - self::DRIFT_STEPS); $step <= $currentStep + self::DRIFT_STEPS; $step++) {
            if (hash_equals($totp->at($step * self::PERIOD_SECONDS), $code) && $step > $lastAcceptedStep && $matched === null) {
                $matched = $step;
            }
        }

        return $matched;
    }

    private function build(string $secret): TOTP
    {
        if ($secret === '') {
            throw new InvalidArgumentException('TOTP secret is required.');
        }
        $clock = new class ($this->clock) implements ClockInterface {
            public function __construct(private readonly Clock $clock)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return (new \DateTimeImmutable())->setTimestamp($this->clock->now());
            }
        };

        return TOTP::create($secret, self::PERIOD_SECONDS, 'sha1', self::DIGITS, 0, $clock);
    }
}
