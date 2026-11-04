<?php

declare(strict_types=1);

namespace SearPHP\Search;

use SearPHP\Http\HttpRequest;

final readonly class EngineOutcome
{
    /**
     * @param list<Result> $results
     * @param list<ImageResult> $images
     * @param list<array{text: string, source?: string, url?: string}> $answers
     * @param list<string> $suggestions
     */
    public function __construct(
        public array $results = [],
        public array $images = [],
        public array $answers = [],
        public array $suggestions = [],
        public ?HttpRequest $followUp = null,
        public string $status = 'ok',
        public ?string $error = null,
        public int $ms = 0,
    ) {
    }

    public static function error(string $message, string $status = 'error'): self
    {
        return new self(status: $status, error: $message);
    }

    public static function empty(): self
    {
        return new self(status: 'empty');
    }

    public function count(): int
    {
        return count($this->results) + count($this->images);
    }
}
