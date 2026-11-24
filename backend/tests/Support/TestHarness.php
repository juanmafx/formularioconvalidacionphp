<?php

declare(strict_types=1);

namespace ContactForm\Tests\Support;

use ContactForm\Admin\AdminAuthenticator;
use ContactForm\Admin\RecoveryCodes;
use ContactForm\Application;
use ContactForm\Config\AppConfig;
use ContactForm\Http\Request;
use ContactForm\Http\Response;
use ContactForm\Security\Session\ArraySessionStore;
use OTPHP\TOTP;

final class TestHarness
{
    public const string USERNAME = 'site-admin';
    public const string PASSWORD = 'correct-horse-battery-staple';
    public const string TOTP_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public Application $application;
    public ArraySessionStore $session;
    public FixedClock $clock;
    public RecordingMailer $mailer;
    public array $recoveryCodes = [];

    public function __construct()
    {
        $this->session = new ArraySessionStore();
        $this->clock = new FixedClock();
        $this->mailer = new RecordingMailer();
        $this->application = new Application(self::config(), self::projectRoot(), $this->session, $this->clock, $this->mailer);
        $this->application->migrate();
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function config(array $overrides = []): AppConfig
    {
        return AppConfig::fromEnvironment($overrides + [
            'APP_KEYRING' => 'k1:' . base64_encode(str_repeat("\x42", 32)),
            'APP_ACTIVE_KEY_ID' => 'k1',
            'DB_PATH' => ':memory:',
            'ARGON_MEMORY_KIB' => '19456',
            'ARGON_TIME_COST' => '2',
        ], self::projectRoot());
    }

    public function createAdmin(): void
    {
        $this->recoveryCodes = RecoveryCodes::generate();
        $this->application->adminUsers->create(
            self::USERNAME,
            $this->application->passwords->hash(self::PASSWORD),
            $this->application->cipher->encrypt(self::TOTP_SECRET, AdminAuthenticator::totpContext(self::USERNAME)),
            array_map(RecoveryCodes::hash(...), $this->recoveryCodes),
        );
    }

    public function currentCode(int $offsetSteps = 0): string
    {
        $totp = TOTP::create(self::TOTP_SECRET);

        return $totp->at($this->clock->now() + $offsetSteps * 30);
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->application->kernel->handle(new Request('GET', $path, $query, [], '198.51.100.7'));
    }

    public function post(string $path, array $data, string $tokenPage = '/', string $address = '198.51.100.7'): Response
    {
        $token = self::extractToken($this->get($tokenPage)->body);

        return $this->application->kernel->handle(new Request('POST', $path, [], $data + ['csrf_token' => $token], $address));
    }

    public function signIn(): Response
    {
        $this->post('/admin/login', ['username' => self::USERNAME, 'password' => self::PASSWORD], '/admin/login');

        return $this->post('/admin/two-factor', ['code' => $this->currentCode()], '/admin/two-factor');
    }

    public static function extractToken(string $html): string
    {
        return preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $matches) === 1 ? $matches[1] : '';
    }

    public static function validContact(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+1 (555) 010-0000',
            'address' => '1 Analytical Way',
            'profession' => 'Mathematician',
            'field_one' => 'First',
            'field_two' => 'Second',
            'field_three' => 'Third',
            'message' => "Line one\nLine two",
        ];
    }
}
