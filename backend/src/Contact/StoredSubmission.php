<?php

declare(strict_types=1);

namespace ContactForm\Contact;

final readonly class StoredSubmission
{
    public function __construct(
        public string $id,
        public ContactSubmission $submission,
        public string $notificationStatus,
        public int $createdAt,
    ) {
    }
}
