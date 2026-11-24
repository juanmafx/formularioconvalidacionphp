<?php

declare(strict_types=1);

namespace ContactForm\Contact;

use ContactForm\Mail\SubmissionMailer;
use Throwable;

final readonly class ContactService
{
    public function __construct(
        private SubmissionRepository $submissions,
        private SubmissionMailer $mailer,
    ) {
    }

    public function accept(ContactSubmission $submission): string
    {
        $id = $this->submissions->store($submission);
        if (!$this->mailer->isEnabled()) {
            $this->submissions->markNotification($id, 'disabled');

            return $id;
        }
        try {
            $this->mailer->send($id, $submission);
            $this->submissions->markNotification($id, 'sent');
        } catch (Throwable $exception) {
            error_log('Submission notification failed: ' . $exception::class);
            $this->submissions->markNotification($id, 'failed');
        }

        return $id;
    }
}
