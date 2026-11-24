<?php

declare(strict_types=1);

namespace ContactForm\Mail;

use ContactForm\Config\AppConfig;
use ContactForm\Contact\ContactSubmission;
use PHPMailer\PHPMailer\PHPMailer;

final readonly class SmtpSubmissionMailer implements SubmissionMailer
{
    private const int SMTP_TIMEOUT_SECONDS = 10;
    private const array LABELS = [
        'name' => 'Name',
        'address' => 'Current address',
        'phone' => 'Phone',
        'email' => 'Email',
        'profession' => 'Profession',
        'field_one' => 'Field one',
        'field_two' => 'Field two',
        'field_three' => 'Field three',
        'message' => 'Message',
    ];

    public function __construct(private AppConfig $config)
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function send(string $submissionId, ContactSubmission $submission): void
    {
        $this->compose($submissionId, $submission)->send();
    }

    public function compose(string $submissionId, ContactSubmission $submission): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Timeout = self::SMTP_TIMEOUT_SECONDS;
        $mailer->getSMTPInstance()->Timelimit = self::SMTP_TIMEOUT_SECONDS;
        $mailer->Host = $this->config->mailHost;
        $mailer->Port = $this->config->mailPort;
        $mailer->SMTPSecure = $this->config->mailEncryption === 'smtps' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAutoTLS = false;
        $mailer->SMTPAuth = $this->config->mailUsername !== '';
        $mailer->Username = $this->config->mailUsername;
        $mailer->Password = $this->config->mailPassword;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->XMailer = ' ';
        $mailer->setFrom($this->config->mailFrom, 'Contact Form', false);
        $mailer->addAddress($this->config->mailTo);
        $mailer->Subject = 'New contact form submission';
        if ($this->config->mailIncludeContent) {
            $mailer->addReplyTo($submission->email, $submission->name);
            $mailer->isHTML(true);
            $mailer->Body = $this->htmlBody($submission);
            $mailer->AltBody = $this->textBody($submission);

            return $mailer;
        }
        $mailer->isHTML(false);
        $mailer->Body = 'A new submission is available in the admin area. Reference: ' . $submissionId;

        return $mailer;
    }

    private function htmlBody(ContactSubmission $submission): string
    {
        $rows = '';
        foreach ($submission->toArray() as $field => $value) {
            $rows .= sprintf(
                '<p>%s: <strong>%s</strong></p>',
                self::LABELS[$field],
                nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')),
            );
        }

        return '<html><body><h2>New contact form submission</h2><hr>' . $rows . '<hr></body></html>';
    }

    private function textBody(ContactSubmission $submission): string
    {
        $lines = [];
        foreach ($submission->toArray() as $field => $value) {
            $lines[] = self::LABELS[$field] . ': ' . $value;
        }

        return implode("\n", $lines);
    }
}
