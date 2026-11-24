<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Security\TotpVerifier;
use ContactForm\Tests\Support\FixedClock;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpVerifierTest extends TestCase
{
    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public static function rfc6238Vectors(): iterable
    {
        yield [59, '287082'];
        yield [1111111109, '081804'];
        yield [1111111111, '050471'];
        yield [1234567890, '005924'];
        yield [2000000000, '279037'];
    }

    #[DataProvider('rfc6238Vectors')]
    public function testAcceptsRfc6238Vectors(int $timestamp, string $code): void
    {
        $verifier = new TotpVerifier(new FixedClock($timestamp));

        self::assertSame(intdiv($timestamp, 30), $verifier->matchingStep(self::SECRET, $code, -1));
    }

    public function testRejectsReplayOfAcceptedStep(): void
    {
        $clock = new FixedClock(1234567890);
        $verifier = new TotpVerifier($clock);

        self::assertNull($verifier->matchingStep(self::SECRET, '005924', intdiv($clock->now(), 30)));
    }

    public function testAcceptsOneStepOfDrift(): void
    {
        $clock = new FixedClock(1234567890 + 30);

        self::assertNotNull((new TotpVerifier($clock))->matchingStep(self::SECRET, '005924', 0));
    }

    public function testRejectsCodeOutsideDriftWindow(): void
    {
        $clock = new FixedClock(1234567890 + 120);

        self::assertNull((new TotpVerifier($clock))->matchingStep(self::SECRET, '005924', 0));
    }

    public function testRejectsMalformedCodes(): void
    {
        $verifier = new TotpVerifier(new FixedClock(59));

        foreach (['', '12345', '1234567', 'abcdef', "287082\n"] as $code) {
            self::assertNull($verifier->matchingStep(self::SECRET, $code, -1));
        }
    }

    public function testGeneratedSecretHas160Bits(): void
    {
        $secret = (new TotpVerifier(new FixedClock()))->generateSecret();

        self::assertGreaterThanOrEqual(32, strlen($secret));
        self::assertSame(6, strlen(TOTP::createFromSecret($secret)->at(1)));
    }

    public function testProvisioningUriCarriesIssuerAndAccount(): void
    {
        $uri = (new TotpVerifier(new FixedClock()))->provisioningUri(self::SECRET, 'site-admin', 'Contact Form');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('site-admin', $uri);
        self::assertStringContainsString('issuer=Contact', $uri);
    }
}
