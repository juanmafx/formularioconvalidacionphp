<?php

declare(strict_types=1);

namespace ContactForm\Security;

use ContactForm\Security\Session\SessionStore;

final readonly class CsrfGuard
{
    private const string SESSION_KEY = 'csrf.token';

    public function __construct(private SessionStore $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (is_string($token)) {
            return $token;
        }

        return $this->rotate();
    }

    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(mixed $candidate): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && is_string($candidate) && hash_equals($token, $candidate);
    }
}
