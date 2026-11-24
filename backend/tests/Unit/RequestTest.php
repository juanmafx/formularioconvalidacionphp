<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    #[DataProvider('contentLengths')]
    public function testParsesDeclaredContentLength(?string $header, ?int $expected): void
    {
        unset($_SERVER['CONTENT_LENGTH']);
        if ($header !== null) {
            $_SERVER['CONTENT_LENGTH'] = $header;
        }

        self::assertSame($expected, Request::fromGlobals()->contentLength);
    }

    public static function contentLengths(): array
    {
        return [
            'missing' => [null, null],
            'empty' => ['', null],
            'negative' => ['-1', null],
            'not numeric' => ['12abc', null],
            'zero' => ['0', 0],
            'declared' => ['2048', 2048],
        ];
    }
}
