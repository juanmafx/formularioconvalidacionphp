<?php

declare(strict_types=1);

namespace ContactForm\Tests\Unit;

use ContactForm\View\TemplateRenderer;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase
{
    public function testEscapesMarkupAndQuotes(): void
    {
        $escaped = TemplateRenderer::escape('"><script>alert(\'x\')</script>');

        self::assertStringNotContainsString('<', $escaped);
        self::assertStringNotContainsString('"', $escaped);
        self::assertStringNotContainsString("'", $escaped);
    }

    public function testHandlesInvalidUtf8AndNonScalars(): void
    {
        self::assertNotSame('', TemplateRenderer::escape("\xC3\x28"));
        self::assertSame('', TemplateRenderer::escape(['array']));
    }
}
