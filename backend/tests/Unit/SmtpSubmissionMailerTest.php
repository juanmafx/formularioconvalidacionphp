<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Contact\ContactSubmission;
use ContactForm\Mail\SmtpSubmissionMailer;
use ContactForm\Tests\Support\TestHarness;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class SmtpSubmissionMailerTest extends TestCase
{
    private const string SUBMISSION_ID = '0123456789abcdef0123456789abcdef';

    private static function mailer(bool $includeContent): SmtpSubmissionMailer
    {
        return new SmtpSubmissionMailer(TestHarness::config([
            'MAIL_ENABLED' => 'true',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM' => 'form@example.test',
            'MAIL_TO' => 'inbox@example.test',
            'MAIL_INCLUDE_CONTENT' => $includeContent ? 'true' : 'false',
        ]));
    }

    private static function submission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::fromArray($overrides + TestHarness::validContact());
    }

    private static function message(PHPMailer $mailer): string
    {
        self::assertTrue($mailer->preSend());

        return $mailer->getSentMIMEMessage();
    }

    public function testDefaultNotificationOmitsPersonalData(): void
    {
        $message = self::message(self::mailer(false)->compose(self::SUBMISSION_ID, self::submission()));

        self::assertStringContainsString(self::SUBMISSION_ID, $message);
        self::assertStringNotContainsString('ada@example.com', $message);
        self::assertStringNotContainsString('Ada Lovelace', $message);
    }

    public function testSenderIsNeverTheVisitor(): void
    {
        $mailer = self::mailer(true)->compose(self::SUBMISSION_ID, self::submission());

        self::assertSame('form@example.test', $mailer->From);
        self::assertContains('ada@example.com', array_column($mailer->getReplyToAddresses(), 0));
    }

    public function testEscapesHtmlInBody(): void
    {
        $mailer = self::mailer(true)->compose(self::SUBMISSION_ID, self::submission(['message' => '<script>alert(1)</script>']));

        self::assertStringNotContainsString('<script>', $mailer->Body);
        self::assertStringContainsString('&lt;script&gt;', $mailer->Body);
    }

    public function testRequiresTransportEncryption(): void
    {
        $mailer = self::mailer(false)->compose(self::SUBMISSION_ID, self::submission());

        self::assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure);
    }

    public function testHeaderValuesStayOnOneLine(): void
    {
        $submission = self::submission(['name' => "Ada\r\nBcc: victim@example.test"]);
        $message = self::message(self::mailer(true)->compose(self::SUBMISSION_ID, $submission));
        $headers = strstr($message, "\r\n\r\n", true);

        self::assertIsString($headers);
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', $headers);
    }
}
