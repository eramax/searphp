<?php

declare(strict_types=1);

namespace SearPHP\Http;

final readonly class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $headers = [],
        public ?string $body = null,
        public int $timeoutMs = 1800,
        public string $engine = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function get(string $url, array $headers = [], int $timeoutMs = 1800, string $engine = ''): self
    {
        return new self($url, 'GET', $headers, null, $timeoutMs, $engine);
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $headers
     */
    public static function postForm(string $url, array $data, array $headers = [], int $timeoutMs = 1800, string $engine = ''): self
    {
        return new self(
            $url,
            'POST',
            array_merge(['Content-Type' => 'application/x-www-form-urlencoded'], $headers),
            http_build_query($data, '', '&', PHP_QUERY_RFC3986),
            $timeoutMs,
            $engine
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function postJson(string $url, array $data, array $headers = [], int $timeoutMs = 1800, string $engine = ''): self
    {
        return new self(
            $url,
            'POST',
            array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $headers),
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $timeoutMs,
            $engine
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function withTimeout(int $timeoutMs): self
    {
        return new self($this->url, $this->method, $this->headers, $this->body, $timeoutMs, $this->engine);
    }
}
