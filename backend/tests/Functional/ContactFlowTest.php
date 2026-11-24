<?php

declare(strict_types=1);

namespace ContactForm\Tests\Functional;

use ContactForm\Http\Request;
use ContactForm\Tests\Support\TestHarness;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContactFlowTest extends TestCase
{
    private TestHarness $harness;

    protected function setUp(): void
    {
        $this->harness = new TestHarness();
    }

    private function storedPayloads(): array
    {
        $statement = $this->harness->application->database->query('SELECT payload_encrypted FROM submissions');
        self::assertNotFalse($statement);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testRendersFormWithCsrfTokenAndSecurityHeaders(): void
    {
        $response = $this->harness->get('/');

        self::assertSame(200, $response->status);
        self::assertNotSame('', TestHarness::extractToken($response->body));
        self::assertStringContainsString("default-src 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function testValidSubmissionIsEncryptedAtRestAndNotified(): void
    {
        $response = $this->harness->post('/', TestHarness::validContact());
        $payloads = $this->storedPayloads();

        self::assertSame(303, $response->status);
        self::assertSame('/?sent=1', $response->headers['Location']);
        self::assertCount(1, $payloads);
        self::assertStringNotContainsString('ada@example.com', $payloads[0]);
        self::assertStringStartsWith('v1.k1.', $payloads[0]);
        self::assertCount(1, $this->harness->mailer->sent);
    }

    public function testKeepsAllThreeCustomFields(): void
    {
        $this->harness->post('/', TestHarness::validContact());
        $stored = $this->harness->application->submissions->page(1, 1)[0]->submission;

        self::assertSame(['First', 'Second', 'Third'], [$stored->fieldOne, $stored->fieldTwo, $stored->fieldThree]);
    }

    public function testRejectsMissingCsrfToken(): void
    {
        $this->harness->get('/');
        $response = $this->harness->application->kernel->handle(new Request('POST', '/', [], TestHarness::validContact()));

        self::assertSame(403, $response->status);
        self::assertSame([], $this->storedPayloads());
    }

    public function testInvalidSubmissionIsNotStoredAndEscapesEchoedValues(): void
    {
        $response = $this->harness->post('/', TestHarness::validContact([
            'email' => 'invalid',
            'address' => '"><script>alert(1)</script>',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringNotContainsString('<script>', $response->body);
        self::assertSame([], $this->storedPayloads());
    }

    public function testHoneypotSilentlyDropsSubmission(): void
    {
        $response = $this->harness->post('/', TestHarness::validContact(['website' => 'https://spam.example']));

        self::assertSame(303, $response->status);
        self::assertSame([], $this->storedPayloads());
        self::assertSame([], $this->harness->mailer->sent);
    }

    public function testRateLimitsSubmissions(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            self::assertSame(303, $this->harness->post('/', TestHarness::validContact())->status);
        }
        $blocked = $this->harness->post('/', TestHarness::validContact());

        self::assertSame(429, $blocked->status);
        self::assertSame('600', $blocked->headers['Retry-After']);
        self::assertSame(303, $this->harness->post('/', TestHarness::validContact(), '/', '203.0.113.50')->status);
    }

    public function testMailFailureKeepsSubmissionAndRecordsStatus(): void
    {
        $this->harness->mailer->shouldFail = true;
        $previousLog = ini_set('error_log', '/dev/null');
        $response = $this->harness->post('/', TestHarness::validContact());
        ini_set('error_log', (string) $previousLog);

        self::assertSame(303, $response->status);
        self::assertSame('failed', $this->harness->application->submissions->page(1, 1)[0]->notificationStatus);
    }

    public function testRejectsOversizedBody(): void
    {
        $response = $this->harness->application->kernel->handle(new Request('POST', '/', [], [], '198.51.100.7', 70000));

        self::assertSame(413, $response->status);
    }

    public function testRejectsPostWithoutDeclaredLength(): void
    {
        $response = $this->harness->application->kernel->handle(new Request('POST', '/', [], [], '198.51.100.7', null));

        self::assertSame(411, $response->status);
    }

    public function testRateLimitCoversWholeIpv6Network(): void
    {
        for ($host = 1; $host <= 10; $host++) {
            $address = '2001:db8:1:2::' . dechex($host);
            self::assertSame(303, $this->harness->post('/', TestHarness::validContact(), '/', $address)->status);
        }

        self::assertSame(429, $this->harness->post('/', TestHarness::validContact(), '/', '2001:db8:1:2::ff')->status);
        self::assertSame(303, $this->harness->post('/', TestHarness::validContact(), '/', '2001:db8:1:3::1')->status);
    }

    public function testStrictTransportSecurityExcludesSubdomains(): void
    {
        self::assertSame('max-age=31536000', $this->harness->get('/')->headers['Strict-Transport-Security']);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        self::assertSame(404, $this->harness->get('/index.php.bak')->status);
    }
}
