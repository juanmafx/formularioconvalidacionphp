<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Config\AppConfig;
use ContactForm\Config\ConfigurationException;
use ContactForm\Tests\Support\TestHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testAppliesSecureDefaults(): void
    {
        $config = AppConfig::fromEnvironment(['APP_KEYRING' => 'k1:x', 'APP_ACTIVE_KEY_ID' => 'k1'], '/srv/app');

        self::assertTrue($config->secureCookies);
        self::assertFalse($config->mailEnabled);
        self::assertFalse($config->mailIncludeContent);
        self::assertSame(65536, $config->argonMemoryKib);
        self::assertSame('/srv/app/var/contact.sqlite', $config->databasePath);
    }

    public static function invalidEnvironments(): iterable
    {
        yield 'weak argon memory' => [['ARGON_MEMORY_KIB' => '1024']];
        yield 'weak argon time' => [['ARGON_TIME_COST' => '1']];
        yield 'bad boolean' => [['APP_SECURE_COOKIES' => 'maybe']];
        yield 'plaintext smtp' => [['MAIL_ENABLED' => 'true', 'MAIL_HOST' => 'h', 'MAIL_FROM' => 'a@example.test', 'MAIL_TO' => 'b@example.test', 'MAIL_ENCRYPTION' => 'none']];
        yield 'missing mail host' => [['MAIL_ENABLED' => 'true', 'MAIL_FROM' => 'a@example.test', 'MAIL_TO' => 'b@example.test']];
        yield 'invalid mail recipient' => [['MAIL_ENABLED' => 'true', 'MAIL_HOST' => 'h', 'MAIL_FROM' => 'a@example.test', 'MAIL_TO' => 'nope']];
    }

    #[DataProvider('invalidEnvironments')]
    public function testRejectsUnsafeConfiguration(array $overrides): void
    {
        $this->expectException(ConfigurationException::class);
        TestHarness::config($overrides);
    }

    public function testRequiresKeyring(): void
    {
        $this->expectException(ConfigurationException::class);
        AppConfig::fromEnvironment([], '/srv/app');
    }
}
