<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Admin\RecoveryCodes;
use PHPUnit\Framework\TestCase;

final class RecoveryCodesTest extends TestCase
{
    public function testGeneratesUniqueFormattedCodes(): void
    {
        $codes = RecoveryCodes::generate();

        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){3}\z/', $code);
        }
    }

    public function testNormalizationIgnoresCaseAndSeparators(): void
    {
        $code = RecoveryCodes::generate(1)[0];
        $variant = strtolower(str_replace('-', ' ', $code));

        self::assertSame(RecoveryCodes::hash($code), RecoveryCodes::hash($variant));
    }

    public function testRejectsInvalidCodes(): void
    {
        foreach (['', 'short', '123456', 'IIII-OOOO-0000-1111'] as $code) {
            self::assertNull(RecoveryCodes::normalize($code));
        }
    }
}
