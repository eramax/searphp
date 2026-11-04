<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;
use SearPHP\Search\Result;

final class Wikipedia extends AbstractEngine
{
    public function id(): string
    {
        return 'wikipedia';
    }

    public function label(): string
    {
        return 'Wikipedia';
    }

    public function categories(): array
    {
        return ['web'];
    }

    public function paginationSupported(): bool
    {
        return false;
    }

    public function pageSize(): int
    {
        return 20;
    }

    public function request(Query $query): ?HttpRequest
    {
        $lang = $this->language($query->lang);
        $params = [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $query->q,
            'format' => 'json',
            'formatversion' => '2',
            'srlimit' => (string) min(20, max(5, $query->limit)),
            'srprop' => 'snippet|timestamp',
            'utf8' => '1',
        ];

        return HttpRequest::get(
            'https://' . $lang . '.wikipedia.org/w/api.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $this->browserHeaders(
                $this->browserUserAgent(),
                'application/json,text/plain,*/*;q=0.8'
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

        if (!$response->ok()) {
            return new EngineOutcome(
                status: $response->isRateLimited() ? 'rate_limited' : 'error',
                error: 'HTTP ' . $response->status . ' from Wikipedia',
                ms: (int) $response->ms
            );
        }

        $data = $response->json();
        if (!is_array($data)) {
            return new EngineOutcome(status: 'error', error: 'Invalid Wikipedia JSON', ms: (int) $response->ms);
        }

        if (isset($data['error'])) {
            return new EngineOutcome(status: 'error', error: 'Wikipedia API error', ms: (int) $response->ms);
        }

        $items = $data['query']['search'] ?? [];
        if (!is_array($items)) {
            return new EngineOutcome(status: 'empty', ms: (int) $response->ms);
        }

        $lang = $this->language($query->lang);
        $results = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['title'])) {
                continue;
            }

            $title = (string) $item['title'];
            $snippet = isset($item['snippet']) ? strip_tags((string) $item['snippet']) : '';
            $snippet = $this->clean($snippet);
            $url = 'https://' . $lang . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title));

            $results[] = new Result(
                $title,
                $url,
                $snippet,
                $this->id(),
                count($results) + 1,
                isset($item['timestamp']) ? (string) $item['timestamp'] : null
            );
        }

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }

    private function language(string $lang): string
    {
        $language = strtolower(explode('-', $lang)[0]);

        return preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : 'en';
    }
}
