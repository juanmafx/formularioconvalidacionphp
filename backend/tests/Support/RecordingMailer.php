<?php

declare(strict_types=1);

namespace ContactForm\Tests\Support;

use ContactForm\Contact\ContactSubmission;
use ContactForm\Mail\SubmissionMailer;
use RuntimeException;

final class RecordingMailer implements SubmissionMailer
{
    public array $sent = [];
    public bool $shouldFail = false;

    public function isEnabled(): bool
    {
        return true;
    }

    public function send(string $submissionId, ContactSubmission $submission): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Transport failure');
        }
        $this->sent[] = $submissionId;
    }
}
