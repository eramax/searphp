<?php

declare(strict_types=1);

namespace SearPHP\Search;

final class Result
{
    /** @var list<string> */
    public array $engines = [];
    public float $score = 0.0;

    public function __construct(
        public string $title,
        public string $url,
        public string $snippet,
        public string $engine,
        public int $rank = 1,
        public ?string $publishedAt = null,
        public ?string $thumbnail = null,
    ) {
        $this->engines = [$engine];
    }

    public function merge(self $other): void
    {
        foreach ($other->engines as $engine) {
            if (!in_array($engine, $this->engines, true)) {
                $this->engines[] = $engine;
            }
        }

        if (strlen($other->snippet) > strlen($this->snippet)) {
            $this->snippet = $other->snippet;
        }

        if ($this->thumbnail === null && $other->thumbnail !== null) {
            $this->thumbnail = $other->thumbnail;
        }

        if ($this->publishedAt === null && $other->publishedAt !== null) {
            $this->publishedAt = $other->publishedAt;
        }

        if (str_starts_with($this->url, 'http://') && str_starts_with($other->url, 'https://')) {
            $this->url = $other->url;
        }

        $this->score += $other->score;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'snippet' => $this->snippet,
            'engine' => $this->engine,
            'engines' => $this->engines,
            'rank' => $this->rank,
            'score' => round($this->score, 6),
            'published_at' => $this->publishedAt,
            'thumbnail' => $this->thumbnail,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            (string) ($data['title'] ?? ''),
            (string) ($data['url'] ?? ''),
            (string) ($data['snippet'] ?? ''),
            (string) ($data['engine'] ?? ''),
            (int) ($data['rank'] ?? 1),
            isset($data['published_at']) ? (string) $data['published_at'] : null,
            isset($data['thumbnail']) ? (string) $data['thumbnail'] : null,
        );

        $result->engines = array_values(array_map('strval', (array) ($data['engines'] ?? [$result->engine])));
        $result->score = (float) ($data['score'] ?? 0.0);

        return $result;
    }
}
