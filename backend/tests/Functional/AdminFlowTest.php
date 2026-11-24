<?php

declare(strict_types=1);

namespace ContactForm\Tests\Functional;

use ContactForm\Contact\ContactSubmission;
use ContactForm\Tests\Support\TestHarness;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminFlowTest extends TestCase
{
    private TestHarness $harness;

    protected function setUp(): void
    {
        $this->harness = new TestHarness();
        $this->harness->createAdmin();
    }

    private function passwordStep(string $password = TestHarness::PASSWORD): int
    {
        return $this->harness->post('/admin/login', ['username' => TestHarness::USERNAME, 'password' => $password], '/admin/login')->status;
    }

    private function codeStep(string $code): int
    {
        return $this->harness->post('/admin/two-factor', ['code' => $code], '/admin/two-factor')->status;
    }

    private function storeSubmission(array $overrides = []): string
    {
        return $this->harness->application->submissions->store(ContactSubmission::fromArray($overrides + TestHarness::validContact()));
    }

    private function auditCount(string $eventType): int
    {
        $statement = $this->harness->application->database->prepare('SELECT COUNT(*) FROM audit_events WHERE event_type = :type');
        $statement->execute(['type' => $eventType]);

        return (int) $statement->fetchColumn();
    }

    public function testAdminPagesRequireAuthentication(): void
    {
        $id = $this->storeSubmission();

        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
        self::assertSame('/admin/login', $this->harness->get('/admin/submissions/' . $id)->headers['Location']);
    }

    public function testPasswordAloneDoesNotGrantAccess(): void
    {
        self::assertSame(303, $this->passwordStep());

        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
    }

    public function testSecondFactorPageRequiresPasswordStep(): void
    {
        self::assertSame('/admin/login', $this->harness->get('/admin/two-factor')->headers['Location']);
    }

    public function testFullSignInShowsDecryptedSubmissionAndAudits(): void
    {
        $id = $this->storeSubmission();
        self::assertSame('/admin', $this->harness->signIn()->headers['Location']);

        $list = $this->harness->get('/admin');
        $detail = $this->harness->get('/admin/submissions/' . $id);

        self::assertStringContainsString('Ada Lovelace', $list->body);
        self::assertStringContainsString('ada@example.com', $detail->body);
        self::assertSame(1, $this->auditCount('login_succeeded'));
        self::assertSame(1, $this->auditCount('submission_viewed'));
    }

    public function testListingSubmissionsIsAudited(): void
    {
        $this->harness->signIn();
        $this->harness->get('/admin');

        self::assertSame(1, $this->auditCount('submission_list_viewed'));
    }

    public function testStoredXssIsEscapedInAdminViews(): void
    {
        $id = $this->storeSubmission(['name' => '<img src=x onerror=alert(1)>', 'message' => '<script>alert(1)</script>']);
        $this->harness->signIn();

        self::assertStringNotContainsString('<img src=x', $this->harness->get('/admin')->body);
        self::assertStringNotContainsString('<script>alert', $this->harness->get('/admin/submissions/' . $id)->body);
    }

    public function testSessionIsRegeneratedAtEachPrivilegeChange(): void
    {
        $this->harness->signIn();

        self::assertSame(2, $this->harness->session->regenerations);
    }

    public function testWrongPasswordAndUnknownUserAreIndistinguishable(): void
    {
        $wrong = $this->harness->post('/admin/login', ['username' => TestHarness::USERNAME, 'password' => 'wrong-password-value'], '/admin/login');
        $unknown = $this->harness->post('/admin/login', ['username' => 'nobody', 'password' => 'wrong-password-value'], '/admin/login');

        self::assertSame(401, $wrong->status);
        self::assertSame(401, $unknown->status);
        self::assertSame(
            preg_replace('/value="[a-f0-9]{64}"/', '', $wrong->body),
            preg_replace('/value="[a-f0-9]{64}"/', '', $unknown->body),
        );
    }

    public function testNullBytePasswordDoesNotError(): void
    {
        $response = $this->harness->post('/admin/login', ['username' => 'nobody', 'password' => "bad\0password-value"], '/admin/login');

        self::assertSame(401, $response->status);
    }

    public function testRejectsInvalidTotp(): void
    {
        $this->passwordStep();
        $invalid = $this->harness->currentCode() === '000000' ? '000001' : '000000';

        self::assertSame(401, $this->codeStep($invalid));
        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
        self::assertSame(1, $this->auditCount('login_second_factor_failed'));
    }

    public function testRejectsReplayedTotp(): void
    {
        $code = $this->harness->currentCode();
        $this->harness->signIn();
        $this->harness->post('/admin/logout', [], '/admin');
        $this->passwordStep();

        self::assertSame(401, $this->codeStep($code));
    }

    public function testPendingSecondFactorExpires(): void
    {
        $this->passwordStep();
        $this->harness->clock->advance(301);

        self::assertSame('/admin/login', $this->harness->get('/admin/two-factor')->headers['Location']);
    }

    public function testRecoveryCodeWorksExactlyOnce(): void
    {
        $code = $this->harness->recoveryCodes[0];
        $this->passwordStep();
        self::assertSame(303, $this->codeStep($code));
        self::assertSame(1, $this->auditCount('recovery_code_used'));
        $this->harness->post('/admin/logout', [], '/admin');

        $this->passwordStep();
        self::assertSame(401, $this->codeStep($code));
    }

    public function testAccountLocksAfterRepeatedPasswordFailures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(401, $this->passwordStep('wrong-password-value'));
        }

        self::assertSame(401, $this->passwordStep());

        $this->harness->clock->advance(901);
        self::assertSame(303, $this->passwordStep());
    }

    public function testFifthSecondFactorFailureLocksTheAccount(): void
    {
        $this->passwordStep();
        $invalid = $this->harness->currentCode() === '000000' ? '000001' : '000000';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(401, $this->codeStep($invalid));
        }

        self::assertSame(429, $this->harness->post('/admin/two-factor', ['code' => $this->harness->currentCode()], '/admin/login')->status);
        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
        self::assertSame(401, $this->passwordStep());
    }

    public function testLoginIsRateLimitedPerAddress(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->harness->post('/admin/login', ['username' => 'user' . $attempt, 'password' => 'wrong-password-value'], '/admin/login');
        }
        $blocked = $this->harness->post('/admin/login', ['username' => 'another', 'password' => 'wrong-password-value'], '/admin/login');

        self::assertSame(429, $blocked->status);
    }

    public function testIdleSessionExpires(): void
    {
        $this->harness->signIn();
        $this->harness->clock->advance(901);

        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
    }

    public function testAbsoluteSessionLifetimeIsEnforced(): void
    {
        $this->harness->signIn();
        for ($elapsed = 0; $elapsed < 28800; $elapsed += 800) {
            $this->harness->clock->advance(800);
            $this->harness->get('/admin');
        }
        $this->harness->clock->advance(800);

        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
    }

    public function testLogoutEndsSession(): void
    {
        $this->harness->signIn();
        $this->harness->post('/admin/logout', [], '/admin');

        self::assertSame('/admin/login', $this->harness->get('/admin')->headers['Location']);
        self::assertSame(1, $this->auditCount('logout'));
    }

    public function testTamperedStoredRecordFailsClosed(): void
    {
        $first = $this->storeSubmission();
        $second = $this->storeSubmission(['name' => 'Other Person']);
        $database = $this->harness->application->database;
        $statement = $database->prepare('SELECT payload_encrypted FROM submissions WHERE id = :id');
        $statement->execute(['id' => $second]);
        $swap = $database->prepare('UPDATE submissions SET payload_encrypted = :payload WHERE id = :id');
        $swap->execute(['payload' => $statement->fetchColumn(), 'id' => $first]);
        $this->harness->signIn();
        $previousLog = ini_set('error_log', '/dev/null');
        $response = $this->harness->get('/admin/submissions/' . $first);
        ini_set('error_log', (string) $previousLog);

        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('Other Person', $response->body);
    }

    public function testPageParameterHandlesHostileInput(): void
    {
        $this->harness->signIn();

        foreach ([['page' => ['x']], ['page' => '-5'], ['page' => 'abc'], ['page' => '999999']] as $query) {
            self::assertSame(200, $this->harness->get('/admin', $query)->status);
        }
    }

    public function testStoresTotpSecretEncrypted(): void
    {
        $statement = $this->harness->application->database->query('SELECT totp_secret_encrypted FROM admin_users');
        self::assertNotFalse($statement);

        self::assertStringNotContainsString(TestHarness::TOTP_SECRET, (string) $statement->fetch(PDO::FETCH_COLUMN));
    }
}
