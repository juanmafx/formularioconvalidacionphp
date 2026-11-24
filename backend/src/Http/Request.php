<?php

declare(strict_types=1);

namespace ContactForm\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public string $clientAddress = '0.0.0.0',
        public ?int $contentLength = 0,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $declaredLength = (string) ($_SERVER['CONTENT_LENGTH'] ?? '');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            ctype_digit($declaredLength) ? (int) $declaredLength : null,
        );
    }

    public function postString(string $key): string
    {
        $value = $this->post[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
