<?php

declare(strict_types=1);

namespace ContactForm\Contact;

final readonly class ValidationResult
{
    public function __construct(
        public ?ContactSubmission $submission,
        public array $errors,
        public array $values,
    ) {
    }

    public function isValid(): bool
    {
        return $this->submission !== null;
    }
}
