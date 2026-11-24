<?php

declare(strict_types=1);

namespace ContactForm\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function redirect(string $location): self
    {
        return new self(303, '', ['Location' => $location]);
    }

    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->body, $headers + $this->headers);
    }

    public function emit(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
