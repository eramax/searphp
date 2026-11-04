<?php

declare(strict_types=1);

namespace SearPHP\Http;

final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public string $url,
        public float $ms,
        public ?string $error = null,
    ) {
    }

    public static function error(string $url, string $message, float $ms = 0.0): self
    {
        return new self(0, [], '', $url, $ms, $message);
    }

    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public function isChallenge(): bool
    {
        $lower = strtolower($this->body);

        return $this->status === 202
            || str_contains($this->body, 'let jsa')
            || str_contains($this->body, 'x-vqd-hash-1')
            || str_contains($lower, 'anomaly')
            || str_contains($lower, 'unusual traffic');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        if ($this->body === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
