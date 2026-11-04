<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;

final class DuckDuckGoWeb extends AbstractEngine
{
    use DuckDuckGoParsingTrait;

    public function id(): string
    {
        return 'duckduckgo';
    }

    public function label(): string
    {
        return 'DuckDuckGo';
    }

    public function categories(): array
    {
        return ['web'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $body = [
            'q' => $query->q,
            'kl' => $this->langKl($query->lang),
            'b' => '',
        ];

        $df = $this->timeFilter($query->timeRange);
        if ($df !== '') {
            $body['df'] = $df;
        }

        if ($query->page > 1) {
            $offset = ($query->page - 1) * 10;
            $body['s'] = (string) $offset;
            $body['dc'] = (string) ($offset + 1);
        }

        return HttpRequest::postForm(
            'https://html.duckduckgo.com/html/',
            $body,
            $this->browserHeaders(
                $this->ddgUserAgent(),
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'https://html.duckduckgo.com/'
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
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from DuckDuckGo', ms: (int) $response->ms);
        }

        if (str_contains($response->url, 'lite.duckduckgo.com')) {
            return $this->parseLiteResponse($response, $query);
        }

        if ($response->isChallenge()) {
            return new EngineOutcome(
                followUp: $this->liteFallbackRequest($query),
                status: 'challenged',
                error: 'DuckDuckGo returned a challenge; trying Lite',
                ms: (int) $response->ms
            );
        }

        if ($response->status >= 400) {
            return new EngineOutcome(
                status: 'error',
                error: 'HTTP ' . $response->status . ' from DuckDuckGo',
                ms: (int) $response->ms
            );
        }

        $results = $this->parseDuckDuckGoWeb($response->body, $this->id());

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }

    private function parseLiteResponse(HttpResponse $response, Query $query): EngineOutcome
    {
        if ($response->isChallenge()) {
            return new EngineOutcome(
                status: 'challenged',
                error: 'DuckDuckGo Lite also returned a challenge',
                ms: (int) $response->ms
            );
        }

        $results = $this->parseDuckDuckGoLite($response->body, $this->id());

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }

    private function liteFallbackRequest(Query $query): HttpRequest
    {
        $body = [
            'q' => $query->q,
            'kl' => $this->langKl($query->lang),
        ];

        if ($query->page > 1) {
            $body['s'] = (string) (($query->page - 1) * 10);
        }

        return HttpRequest::postForm(
            'https://lite.duckduckgo.com/lite/',
            $body,
            $this->browserHeaders(
                $this->ddgFallbackUserAgent(),
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'https://lite.duckduckgo.com/'
            ),
            $this->timeout(),
            $this->id()
        );
    }
}
