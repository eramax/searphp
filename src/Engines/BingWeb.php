<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use DOMNode;
use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;
use SearPHP\Search\Result;
use SearPHP\Search\UrlCanonicalizer;

final class BingWeb extends AbstractEngine
{
    public function id(): string
    {
        return 'bing';
    }

    public function label(): string
    {
        return 'Bing';
    }

    public function categories(): array
    {
        return ['web'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'count' => '10',
            'first' => (string) (($query->page - 1) * 10 + 1),
            'setlang' => substr($query->lang, 0, 2),
            'form' => 'QBLH',
        ];

        $safe = match ($query->safesearch) {
            1 => 'moderate',
            2 => 'strict',
            default => 'off',
        };
        $params['adlt'] = $safe;

        return HttpRequest::get(
            'https://www.bing.com/search?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $this->browserHeaders(
                $this->browserUserAgent(),
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'https://www.bing.com/'
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
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from Bing', ms: (int) $response->ms);
        }

        $body = $response->body;
        $lower = strtolower($body);
        if ($response->status === 403 || str_contains($lower, 'verify that you are not a robot') || str_contains($lower, 'unusual traffic')) {
            return new EngineOutcome(status: 'challenged', error: 'Bing returned a challenge', ms: (int) $response->ms);
        }

        if ($response->status >= 400) {
            return new EngineOutcome(status: 'error', error: 'HTTP ' . $response->status . ' from Bing', ms: (int) $response->ms);
        }

        $xpath = $this->htmlXPath($body);
        $nodes = $xpath->query('//li[contains(@class, "b_algo")]');
        $results = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMNode) {
                    continue;
                }

                if (str_contains($node->getAttribute('class'), 'b_ad')) {
                    continue;
                }

                $link = $xpath->query('.//h2//a', $node)->item(0);
                if (!$link instanceof DOMNode) {
                    $link = $xpath->query('.//h2', $node)->item(0);
                }
                if (!$link instanceof DOMNode) {
                    continue;
                }

                $href = $link instanceof \DOMElement ? $link->getAttribute('href') : '';
                $url = UrlCanonicalizer::fromRedirect($href);
                if ($url === null) {
                    continue;
                }

                $title = $this->text($link);
                if ($title === '') {
                    $title = $this->clean($link->getAttribute('aria-label'));
                }
                if ($title === '') {
                    continue;
                }

                $snippetNode = $xpath->query('.//div[contains(@class, "b_caption")]//p', $node)->item(0);
                if (!$snippetNode instanceof DOMNode) {
                    $snippetNode = $xpath->query('.//p', $node)->item(0);
                }

                $publishedNode = $xpath->query('.//span[contains(@class, "news_dt")]', $node)->item(0);
                if (!$publishedNode instanceof DOMNode) {
                    $publishedNode = $xpath->query('.//time', $node)->item(0);
                }

                $results[] = new Result(
                    $title,
                    $url,
                    $snippetNode instanceof DOMNode ? $this->text($snippetNode) : '',
                    $this->id(),
                    count($results) + 1,
                    $publishedNode instanceof DOMNode ? $this->text($publishedNode) : null
                );
            }
        }

        if ($results === [] && (str_contains($lower, 'there are no results for') || str_contains($lower, 'no results found for'))) {
            return new EngineOutcome(status: 'empty', ms: (int) $response->ms);
        }

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }
}
