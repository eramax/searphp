<?php

declare(strict_types=1);

namespace SearPHP\Search;

use InvalidArgumentException;
use SearPHP\Config;
use SearPHP\Http\Request;

final readonly class Query
{
    /**
     * @param list<string> $engines
     */
    public function __construct(
        public string $q,
        public array $engines = [],
        public int $limit = 20,
        public int $page = 1,
        public string $lang = 'en',
        public int $safesearch = 0,
        public ?string $timeRange = null,
        public bool $includeImages = false,
        public string $format = 'json',
        public bool $download = false,
        public bool $pretty = false,
    ) {
    }

    /**
     * @param list<string> $availableEngines
     */
    public static function fromRequest(Request $request, Config $config, array $availableEngines): self
    {
        $rawQuery = $request->input('q') ?? $request->input('query') ?? '';
        $rawQuery = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $rawQuery) ?? '';
        $query = trim(preg_replace('/\s+/u', ' ', $rawQuery) ?? '');
        $length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);

        if ($query === '' || $length > 400) {
            throw new InvalidArgumentException('The query must contain between 1 and 400 characters.');
        }

        $rawEngines = $request->input('engines') ?? $request->input('engine') ?? '';
        $engines = [];
        if (trim($rawEngines) !== '') {
            $aliases = [
                'ddg' => 'duckduckgo',
                'wiki' => 'wikipedia',
                'ddg-ia' => 'ddg_instant',
                'ddg-images' => 'duckduckgo_images',
                'ddg_images' => 'duckduckgo_images',
                'bing-images' => 'bing_images',
                'brave-images' => 'brave_images',
            ];

            foreach (explode(',', strtolower($rawEngines)) as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $item = $aliases[$item] ?? $item;
                if (!in_array($item, $availableEngines, true)) {
                    throw new InvalidArgumentException('Unknown engine: ' . $item);
                }
                $engines[] = $item;
            }
            $engines = array_values(array_unique($engines));
        }

        $format = strtolower($request->preferredFormat('json'));
        if ($format === 'markdown') {
            $format = 'md';
        }
        if (!in_array($format, ['md', 'json', 'searx', 'html'], true)) {
            throw new InvalidArgumentException('Unsupported format. Use md, json, searx, or html.');
        }

        $lang = strtolower((string) ($request->input('lang') ?? 'en'));
        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $lang) !== 1) {
            throw new InvalidArgumentException('Invalid language code.');
        }

        $timeRange = $request->input('time') ?? $request->input('time_range');
        if ($timeRange !== null && !in_array($timeRange, ['day', 'week', 'month', 'year'], true)) {
            throw new InvalidArgumentException('Invalid time range. Use day, week, month, or year.');
        }

        $limit = max(1, min(50, $request->int('limit', 20)));
        $page = max(1, min(10, $request->int('page', 1)));
        $safesearch = max(0, min(2, $request->int('safesearch', 0)));

        return new self(
            q: $query,
            engines: $engines,
            limit: $limit,
            page: $page,
            lang: $lang,
            safesearch: $safesearch,
            timeRange: $timeRange,
            includeImages: $request->bool('include_images', false),
            format: $format,
            download: $request->bool('download', false),
            pretty: $request->bool('pretty', false),
        );
    }

    public function withFormat(string $format): self
    {
        return new self(
            $this->q,
            $this->engines,
            $this->limit,
            $this->page,
            $this->lang,
            $this->safesearch,
            $this->timeRange,
            $this->includeImages,
            $format,
            $this->download,
            $this->pretty,
        );
    }

    public function withIncludeImages(bool $includeImages): self
    {
        return new self(
            $this->q,
            $this->engines,
            $this->limit,
            $this->page,
            $this->lang,
            $this->safesearch,
            $this->timeRange,
            $includeImages,
            $this->format,
            $this->download,
            $this->pretty,
        );
    }
}
