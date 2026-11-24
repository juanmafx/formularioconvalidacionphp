<?php

declare(strict_types=1);

namespace ContactForm\Security\Session;

final class ArraySessionStore implements SessionStore
{
    private array $values = [];
    public int $regenerations = 0;

    public function start(): void
    {
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->values = [];
    }
}
