<?php

declare(strict_types=1);

namespace ContactForm\Admin;

final readonly class AdminUser
{
    public function __construct(
        public int $id,
        public string $username,
        public string $passwordHash,
        public string $encryptedTotpSecret,
        public int $totpLastStep,
        public int $lockedUntil,
    ) {
    }
}
