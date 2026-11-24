<?php

declare(strict_types=1);

namespace ContactForm\Support;

interface Clock
{
    public function now(): int;
}
