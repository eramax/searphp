<?php

declare(strict_types=1);

namespace SearPHP\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status = 200,
        private array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        foreach (array_keys($clone->headers) as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                unset($clone->headers[$existing]);
            }
        }
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        foreach ($headers as $name => $value) {
            $clone = $clone->withHeader((string) $name, (string) $value);
        }

        return $clone;
    }

    public function withBody(string $body): self
    {
        return new self($this->status, $this->headers, $body);
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->body;
            return;
        }

        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    public static function json(mixed $data, int $status = 200, bool $pretty = false): self
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $body = json_encode($data, $flags);
        if ($body === false) {
            $body = '{"error":{"code":"encoding_error","message":"Response could not be encoded"}}';
            $status = 500;
        }

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], $body);
    }

    public static function text(string $body, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): self
    {
        return new self($status, ['Content-Type' => $contentType], $body);
    }

    public static function markdown(string $body, int $status = 200): self
    {
        return self::text($body, $status, 'text/markdown; charset=utf-8');
    }

    public static function html(string $body, int $status = 200): self
    {
        return self::text($body, $status, 'text/html; charset=utf-8');
    }

    public static function noContent(int $status = 204): self
    {
        return new self($status);
    }

    public static function error(string $code, string $message, int $status, bool $html = false, array $extra = []): self
    {
        if ($html) {
            $body = '<!doctype html><html lang="en"><meta charset="utf-8"><title>'
                . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</title><body style="font:16px system-ui;padding:2rem;max-width:40rem;margin:auto">'
                . '<h1>' . $status . '</h1><p>'
                . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</p></body></html>';

            return self::html($body, $status);
        }

        return self::json(array_merge([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $extra), $status);
    }
}
