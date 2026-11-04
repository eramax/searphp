<?php

declare(strict_types=1);

namespace SearPHP\Search;

final readonly class SearchResultSet
{
    /**
     * @param list<Result> $results
     * @param list<ImageResult> $images
     * @param list<array{text: string, source?: string, url?: string}> $answers
     * @param list<string> $suggestions
     * @param array<string, array{status: string, ms: int, count: int, error?: string}> $engineStatus
     */
    public function __construct(
        public string $query,
        public array $results = [],
        public array $images = [],
        public array $answers = [],
        public array $suggestions = [],
        public array $engineStatus = [],
        public int $tookMs = 0,
        public bool $cached = false,
        public bool $partial = false,
        public int $page = 1,
        public int $limit = 20,
        public bool $hasMore = false,
        public int $maxPage = 10,
    ) {
    }

    public function withTiming(int $tookMs, bool $cached): self
    {
        return new self(
            $this->query,
            $this->results,
            $this->images,
            $this->answers,
            $this->suggestions,
            $this->engineStatus,
            $tookMs,
            $cached,
            $this->partial,
            $this->page,
            $this->limit,
            $this->hasMore,
            $this->maxPage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'results' => array_map(static fn (Result $result): array => $result->toArray(), $this->results),
            'images' => array_map(static fn (ImageResult $image): array => $image->toArray(), $this->images),
            'answers' => $this->answers,
            'suggestions' => $this->suggestions,
            'engine_status' => $this->engineStatus,
            'took_ms' => $this->tookMs,
            'partial' => $this->partial,
            'page' => $this->page,
            'limit' => $this->limit,
            'has_more' => $this->hasMore,
            'max_page' => $this->maxPage,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, bool $cached = false): self
    {
        $results = [];
        foreach ((array) ($data['results'] ?? []) as $item) {
            if (is_array($item)) {
                $results[] = Result::fromArray($item);
            }
        }

        $images = [];
        foreach ((array) ($data['images'] ?? []) as $item) {
            if (is_array($item)) {
                $images[] = ImageResult::fromArray($item);
            }
        }

        return new self(
            query: (string) ($data['query'] ?? ''),
            results: $results,
            images: $images,
            answers: array_values(array_filter((array) ($data['answers'] ?? []), 'is_array')),
            suggestions: array_values(array_map('strval', (array) ($data['suggestions'] ?? []))),
            engineStatus: (array) ($data['engine_status'] ?? []),
            tookMs: (int) ($data['took_ms'] ?? 0),
            cached: $cached,
            partial: (bool) ($data['partial'] ?? false),
            page: (int) ($data['page'] ?? 1),
            limit: (int) ($data['limit'] ?? 20),
            hasMore: (bool) ($data['has_more'] ?? false),
            maxPage: (int) ($data['max_page'] ?? 10),
        );
    }
}
