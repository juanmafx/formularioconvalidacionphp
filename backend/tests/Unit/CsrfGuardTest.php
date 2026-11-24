<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Security\CsrfGuard;
use ContactForm\Security\Session\ArraySessionStore;
use PHPUnit\Framework\TestCase;

final class CsrfGuardTest extends TestCase
{
    public function testAcceptsIssuedToken(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());

        self::assertTrue($guard->isValid($guard->token()));
    }

    public function testTokenIsStableWithinSession(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());

        self::assertSame($guard->token(), $guard->token());
    }

    public function testRejectsWrongMissingAndNonStringTokens(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());
        $guard->token();

        foreach (['wrong', '', null, ['array'], 1] as $candidate) {
            self::assertFalse($guard->isValid($candidate));
        }
    }

    public function testRejectsWhenNoTokenIssued(): void
    {
        self::assertFalse((new CsrfGuard(new ArraySessionStore()))->isValid(''));
    }

    public function testRotationInvalidatesPreviousToken(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());
        $previous = $guard->token();
        $guard->rotate();

        self::assertFalse($guard->isValid($previous));
    }
}
