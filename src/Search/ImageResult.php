<?php

declare(strict_types=1);

namespace SearPHP\Search;

final class ImageResult
{
    /** @var list<string> */
    public array $engines = [];
    public float $score = 0.0;

    public function __construct(
        public string $title,
        public string $imageUrl,
        public string $thumbnailUrl,
        public string $pageUrl,
        public ?int $width,
        public ?int $height,
        public string $engine,
        public int $rank = 1,
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

        if ($this->width === null) {
            $this->width = $other->width;
        }
        if ($this->height === null) {
            $this->height = $other->height;
        }
        if ($this->thumbnailUrl === '' && $other->thumbnailUrl !== '') {
            $this->thumbnailUrl = $other->thumbnailUrl;
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
            'image_url' => $this->imageUrl,
            'thumbnail_url' => $this->thumbnailUrl,
            'page_url' => $this->pageUrl,
            'width' => $this->width,
            'height' => $this->height,
            'engine' => $this->engine,
            'engines' => $this->engines,
            'rank' => $this->rank,
            'score' => round($this->score, 6),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $image = new self(
            (string) ($data['title'] ?? ''),
            (string) ($data['image_url'] ?? ''),
            (string) ($data['thumbnail_url'] ?? ''),
            (string) ($data['page_url'] ?? ''),
            isset($data['width']) ? (int) $data['width'] : null,
            isset($data['height']) ? (int) $data['height'] : null,
            (string) ($data['engine'] ?? ''),
            (int) ($data['rank'] ?? 1),
        );

        $image->engines = array_values(array_map('strval', (array) ($data['engines'] ?? [$image->engine])));
        $image->score = (float) ($data['score'] ?? 0.0);

        return $image;
    }
}
