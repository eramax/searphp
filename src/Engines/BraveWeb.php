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

final class BraveWeb extends AbstractEngine
{
    public function id(): string
    {
        return 'brave';
    }

    public function label(): string
    {
        return 'Brave Search';
    }

    public function categories(): array
    {
        return ['web'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'source' => 'web',
            'spellcheck' => '0',
        ];

        if ($query->page > 1) {
            $params['offset'] = (string) (($query->page - 1) * 10);
        }

        $safe = match ($query->safesearch) {
            1 => 'moderate',
            2 => 'strict',
            default => 'off',
        };
        $params['safesearch'] = $safe;

        return HttpRequest::get(
            'https://search.brave.com/search?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $this->browserHeaders(
                $this->browserUserAgent(),
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'https://search.brave.com/'
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
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from Brave', ms: (int) $response->ms);
        }

        if ($response->status === 403) {
            return new EngineOutcome(status: 'challenged', error: 'Brave returned HTTP 403', ms: (int) $response->ms);
        }

        if ($response->status >= 400) {
            return new EngineOutcome(status: 'error', error: 'HTTP ' . $response->status . ' from Brave', ms: (int) $response->ms);
        }

        $xpath = $this->htmlXPath($response->body);
        $nodes = $xpath->query('//div[contains(@class, "snippet") and @data-type="web"]');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query(
                '//div[contains(concat(" ", normalize-space(@class), " "), " snippet ")'
                . ' and not(contains(@class, "snippet-divider"))'
                . ' and not(contains(@class, "snippet--ad"))]'
            );
        }

        $results = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMNode) {
                    continue;
                }

                $link = $xpath->query('.//a[@href]', $node)->item(0);
                if (!$link instanceof \DOMElement) {
                    continue;
                }

                $url = UrlCanonicalizer::fromRedirect($link->getAttribute('href'));
                if ($url === null) {
                    continue;
                }

                $titleNode = $xpath->query('.//div[contains(@class, "search-snippet-title")]', $node)->item(0);
                if (!$titleNode instanceof DOMNode) {
                    $titleNode = $xpath->query('.//a[contains(@class, "heading-serpresult")]', $node)->item(0);
                }

                $title = $titleNode instanceof DOMNode ? $this->text($titleNode) : '';
                if ($title === '' && $link instanceof \DOMElement) {
                    $title = $this->clean($link->getAttribute('title'));
                }
                if ($title === '') {
                    $title = $this->text($link);
                }
                if ($title === '') {
                    continue;
                }

                $snippetNode = $xpath->query('.//div[contains(@class, "generic-snippet")]//div[contains(@class, "content")]', $node)->item(0);
                if (!$snippetNode instanceof DOMNode) {
                    $snippetNode = $xpath->query('.//div[contains(@class, "snippet-description")]', $node)->item(0);
                }
                if (!$snippetNode instanceof DOMNode) {
                    $snippetNode = $xpath->query('.//div[contains(@class, "snippet-content")]', $node)->item(0);
                }

                $results[] = new Result(
                    $title,
                    $url,
                    $snippetNode instanceof DOMNode ? $this->text($snippetNode) : '',
                    $this->id(),
                    count($results) + 1
                );
            }
        }

        if ($results === []) {
            $lower = strtolower($response->body);
            if (str_contains($lower, 'unusual traffic') || str_contains($lower, 'verify you are human') || str_contains($lower, 'not a robot')) {
                return new EngineOutcome(status: 'challenged', error: 'Brave returned a challenge', ms: (int) $response->ms);
            }
        }

        return new EngineOutcome(
            results: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }
}
