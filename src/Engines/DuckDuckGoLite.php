<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;

final class DuckDuckGoLite extends AbstractEngine
{
    use DuckDuckGoParsingTrait;

    public function id(): string
    {
        return 'duckduckgo_lite';
    }

    public function label(): string
    {
        return 'DuckDuckGo Lite';
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

    public function parse(HttpResponse $response, Query $query): EngineOutcome
    {
        if ($response->error !== null) {
            return new EngineOutcome(status: 'error', error: $response->error, ms: (int) $response->ms);
        }

        if ($response->isRateLimited()) {
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from DuckDuckGo Lite', ms: (int) $response->ms);
        }

        if ($response->isChallenge()) {
            return new EngineOutcome(status: 'challenged', error: 'DuckDuckGo Lite returned a challenge', ms: (int) $response->ms);
        }

        $results = $this->parseDuckDuckGoLite($response->body, $this->id());

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }
}
