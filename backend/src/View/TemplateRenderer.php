<?php

declare(strict_types=1);

namespace ContactForm\View;

use RuntimeException;

final readonly class TemplateRenderer
{
    public function __construct(private string $templateDirectory)
    {
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    public function page(string $template, string $title, array $variables = []): string
    {
        return $this->render('layout', [
            'title' => $title,
            'content' => $this->render($template, $variables),
        ]);
    }

    private function render(string $template, array $variables): string
    {
        $path = $this->templateDirectory . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Template not found.');
        }
        $renderer = static function (string $__path, array $__variables): void {
            extract($__variables, EXTR_SKIP);
            $e = TemplateRenderer::escape(...);
            require $__path;
        };
        ob_start();
        try {
            $renderer($path, $variables);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
