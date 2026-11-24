<?php

declare(strict_types=1);

namespace ContactForm\Mail;

use ContactForm\Contact\ContactSubmission;

final class DisabledSubmissionMailer implements SubmissionMailer
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function send(string $submissionId, ContactSubmission $submission): void
    {
    }
}
