<?php

declare(strict_types=1);

namespace ContactForm\Mail;

use ContactForm\Contact\ContactSubmission;

interface SubmissionMailer
{
    public function isEnabled(): bool;

    public function send(string $submissionId, ContactSubmission $submission): void;
}
