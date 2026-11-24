<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Application;
use ContactForm\Console\Console;
use ContactForm\Security\Keyring;
use ContactForm\Tests\Support\TestHarness;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private function runConsole(array $arguments, string $stdin, Application $application): array
    {
        $input = fopen('php://memory', 'r+');
        $output = fopen('php://memory', 'r+');
        self::assertIsResource($input);
        self::assertIsResource($output);
        fwrite($input, $stdin);
        rewind($input);
        $status = (new Console(static fn (): Application => $application, $input, $output))->run(['console', ...$arguments]);
        rewind($output);

        return [$status, (string) stream_get_contents($output)];
    }

    public function testGeneratesParsableKey(): void
    {
        [$status, $output] = $this->runConsole(['keys:generate', 'k2'], '', (new TestHarness())->application);

        self::assertSame(0, $status);
        self::assertSame('k2', Keyring::parse(trim($output), 'k2')->activeKeyId);
    }

    public function testCreatesAdminWithEncryptedSecret(): void
    {
        $harness = new TestHarness();
        [$status, $output] = $this->runConsole(['admin:create', 'Operator'], TestHarness::PASSWORD . "\n", $harness->application);
        $user = $harness->application->adminUsers->findByUsername('operator');

        self::assertSame(0, $status);
        self::assertNotNull($user);
        self::assertStringStartsWith('$argon2id$', $user->passwordHash);
        self::assertStringStartsWith('v1.k1.', $user->encryptedTotpSecret);
        self::assertStringContainsString('otpauth://totp/', $output);
    }

    public function testRejectsWeakPassword(): void
    {
        $harness = new TestHarness();
        [$status] = $this->runConsole(['admin:create', 'operator'], "short\n", $harness->application);

        self::assertSame(1, $status);
        self::assertNull($harness->application->adminUsers->findByUsername('operator'));
    }

    public function testRejectsInvalidUsername(): void
    {
        [$status] = $this->runConsole(['admin:create', 'x y'], TestHarness::PASSWORD . "\n", (new TestHarness())->application);

        self::assertSame(1, $status);
    }

    public function testUnknownCommandShowsUsage(): void
    {
        [$status, $output] = $this->runConsole(['nope'], '', (new TestHarness())->application);

        self::assertSame(2, $status);
        self::assertStringContainsString('Commands:', $output);
    }
}
