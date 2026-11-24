<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Contact\ContactValidator;
use ContactForm\Tests\Support\TestHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactValidatorTest extends TestCase
{
    public function testAcceptsValidSubmission(): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact());

        self::assertTrue($result->isValid());
        self::assertSame('Ada Lovelace', $result->submission?->name);
        self::assertSame("Line one\nLine two", $result->submission?->message);
    }

    public function testTrimsWhitespace(): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact(['name' => '  Ada  ']));

        self::assertSame('Ada', $result->submission?->name);
    }

    public function testKeepsNonLatinNames(): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact(['name' => 'Łukasz 山田']));

        self::assertTrue($result->isValid());
    }

    public static function requiredFields(): iterable
    {
        yield ['name'];
        yield ['email'];
        yield ['phone'];
    }

    #[DataProvider('requiredFields')]
    public function testRequiresField(string $field): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact([$field => '']));

        self::assertArrayHasKey($field, $result->errors);
    }

    public static function invalidInput(): iterable
    {
        yield 'email header injection' => ['email', "ada@example.com\r\nBcc: victim@example.com"];
        yield 'name header injection' => ['name', "Ada\r\nBcc: victim@example.com"];
        yield 'name line feed' => ['name', "Ada\nSubject: spoof"];
        yield 'phone header injection' => ['phone', "5550100\r\nX-Test: 1"];
        yield 'address unicode line separator' => ['address', "Street\u{2028}Bcc: x"];
        yield 'name bidi override' => ['name', "Ada\u{202E}evil"];
        yield 'malformed email' => ['email', 'not-an-email'];
        yield 'phone letters' => ['phone', 'call-me-maybe'];
        yield 'phone too short' => ['phone', '123'];
        yield 'invalid utf8' => ['name', "\xC3\x28"];
        yield 'null byte in message' => ['message', "hello\0world"];
        yield 'array input' => ['name', ['nested']];
        yield 'name too long' => ['name', str_repeat('a', 101)];
        yield 'message too long' => ['message', str_repeat('a', 5001)];
    }

    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(string $field, mixed $value): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact([$field => $value]));

        self::assertFalse($result->isValid());
        self::assertArrayHasKey($field, $result->errors);
    }

    public function testAllowsLineBreaksInMultiLineFields(): void
    {
        $result = (new ContactValidator())->validate(TestHarness::validContact(['message' => "a\r\nb\tc"]));

        self::assertTrue($result->isValid());
    }
}
