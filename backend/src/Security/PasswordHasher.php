<?php

declare(strict_types=1);

namespace ContactForm\Security;

use InvalidArgumentException;

final readonly class PasswordHasher
{
    public const int MIN_LENGTH = 12;
    public const int MAX_LENGTH = 256;

    public function __construct(
        private int $memoryKib,
        private int $timeCost,
        private int $threads,
    ) {
    }

    public function hash(string $password): string
    {
        $length = strlen($password);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || str_contains($password, "\0")) {
            throw new InvalidArgumentException(sprintf('Passwords must be %d to %d bytes.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        return password_hash($password, PASSWORD_ARGON2ID, $this->options());
    }

    public function verify(string $password, string $hash): bool
    {
        if (strlen($password) > self::MAX_LENGTH || str_contains($password, "\0")) {
            $this->burnVerification($password);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function burnVerification(string $password): void
    {
        password_hash(hash('sha256', $password), PASSWORD_ARGON2ID, $this->options());
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options());
    }

    private function options(): array
    {
        return [
            'memory_cost' => $this->memoryKib,
            'time_cost' => $this->timeCost,
            'threads' => $this->threads,
        ];
    }
}
