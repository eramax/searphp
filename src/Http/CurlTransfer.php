<?php

declare(strict_types=1);

namespace SearPHP\Http;

final class CurlTransfer
{
    public string $body = '';
    /** @var array<string, string> */
    public array $headers = [];
    public int $status = 0;
    public bool $tooLarge = false;

    public function __construct(
        public readonly HttpRequest $request,
        public readonly int $maxBytes,
    ) {
    }

    public function onHeader(string $line): int
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return strlen($line);
        }

        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $trimmed, $matches) === 1) {
            $this->status = (int) $matches[1];
            $this->headers = [];
            return strlen($line);
        }

        $position = strpos($trimmed, ':');
        if ($position === false) {
            return strlen($line);
        }

        $name = strtolower(trim(substr($trimmed, 0, $position)));
        $value = trim(substr($trimmed, $position + 1));
        if ($name === '') {
            return strlen($line);
        }

        if (isset($this->headers[$name])) {
            $this->headers[$name] .= ', ' . $value;
        } else {
            $this->headers[$name] = $value;
        }

        return strlen($line);
    }

    public function onWrite(string $chunk): int
    {
        if ($this->tooLarge) {
            return 0;
        }

        if (strlen($this->body) + strlen($chunk) > $this->maxBytes) {
            $this->tooLarge = true;
            return 0;
        }

        $this->body .= $chunk;

        return strlen($chunk);
    }
}
