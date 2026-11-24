<?php

declare(strict_types=1);

namespace ContactForm\Security\Session;

use RuntimeException;

final readonly class NativeSessionStore implements SessionStore
{
    public function __construct(private bool $secureCookies)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cache_limiter', '');
        session_name($this->secureCookies ? '__Host-contact_session' : 'contact_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookies,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Session could not be started.');
        }
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        $parameters = session_get_cookie_params();
        setcookie((string) session_name(), '', [
            'expires' => 1,
            'path' => $parameters['path'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'],
        ]);
        session_destroy();
    }
}
