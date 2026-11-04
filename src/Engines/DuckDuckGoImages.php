<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\ImageResult;
use SearPHP\Search\Query;

final class DuckDuckGoImages extends AbstractEngine
{
    public function id(): string
    {
        return 'duckduckgo_images';
    }

    public function label(): string
    {
        return 'DuckDuckGo Images';
    }

    public function categories(): array
    {
        return ['images'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'iax' => 'images',
            'ia' => 'images',
        ];

        return HttpRequest::get(
            'https://duckduckgo.com/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $this->browserHeaders(
                $this->ddgUserAgent(),
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'https://duckduckgo.com/'
            ),
            $this->timeout(),
            $this->id()
        );
    }

    public function parse(HttpResponse $response, Query $query): EngineOutcome
    {
        if ($response->error !== null) {
            return new EngineOutcome(status: 'error', error: $response->error, ms: (int) $response->ms);
        }

        if ($response->isRateLimited()) {
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from DuckDuckGo Images', ms: (int) $response->ms);
        }

        $trimmed = ltrim($response->body);
        if (str_contains($response->url, '/i.js')) {
            if ($response->isChallenge()) {
                return new EngineOutcome(status: 'challenged', error: 'DuckDuckGo Images JSON endpoint returned a challenge', ms: (int) $response->ms);
            }

            return $this->parseJson($response);
        }

        if (str_starts_with($trimmed, '{')) {
            return $this->parseJson($response);
        }

        if ($response->status >= 400) {
            return new EngineOutcome(status: 'error', error: 'HTTP ' . $response->status . ' from DuckDuckGo Images', ms: (int) $response->ms);
        }

        // The image landing page can contain a JS challenge and still expose a
        // valid vqd token. Prefer the token; only report a challenge without it.
        $vqd = $this->extractVqd($response->body);
        if ($vqd === null) {
            if ($response->isChallenge()) {
                return new EngineOutcome(status: 'challenged', error: 'DuckDuckGo Images returned a challenge', ms: (int) $response->ms);
            }

            return new EngineOutcome(status: 'error', error: 'DuckDuckGo image token not found', ms: (int) $response->ms);
        }

        $params = [
            'l' => $this->langKl($query->lang),
            'o' => 'json',
            'q' => $query->q,
            'vqd' => $vqd,
            'f' => ',,,,',
            'p' => (string) $query->page,
        ];

        return new EngineOutcome(
            followUp: HttpRequest::get(
                'https://duckduckgo.com/i.js?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
                $this->browserHeaders(
                    $this->ddgUserAgent(),
                    'application/json, text/javascript, */*; q=0.01',
                    'https://duckduckgo.com/'
                ),
                $this->timeout(),
                $this->id()
            ),
            status: 'ok',
            ms: (int) $response->ms
        );
    }

    private function parseJson(HttpResponse $response): EngineOutcome
    {
        $data = $response->json();
        if (!is_array($data)) {
            return new EngineOutcome(status: 'error', error: 'Invalid DuckDuckGo image JSON', ms: (int) $response->ms);
        }

        $items = $data['results'] ?? [];
        if (!is_array($items)) {
            return new EngineOutcome(status: 'empty', ms: (int) $response->ms);
        }

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $image = trim((string) ($item['image'] ?? ''));
            if ($image === '' || !preg_match('~^https?://~i', $image)) {
                continue;
            }

            $thumbnail = trim((string) ($item['thumbnail'] ?? ''));
            $pageUrl = trim((string) ($item['url'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));

            if ($pageUrl === '' || !preg_match('~^https?://~i', $pageUrl)) {
                $pageUrl = $image;
            }

            $results[] = new ImageResult(
                $title !== '' ? $this->clean($title) : $this->host($pageUrl),
                $image,
                $thumbnail !== '' ? $thumbnail : $image,
                $pageUrl,
                isset($item['width']) ? (int) $item['width'] : null,
                isset($item['height']) ? (int) $item['height'] : null,
                $this->id(),
                count($results) + 1
            );
        }

        return new EngineOutcome(
            images: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }

    private function extractVqd(string $html): ?string
    {
        if (preg_match('/vqd=([\d-]+)/', $html, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('/vqd["\']?\s*[:=]\s*["\']?([\d-]+)/', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : 'image';
    }
}
