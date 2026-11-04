<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use DOMNode;
use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\ImageResult;
use SearPHP\Search\Query;

/**
 * Experimental: Brave rate-limits image pages aggressively. Disabled by default.
 */
final class BraveImages extends AbstractEngine
{
    public function id(): string
    {
        return 'brave_images';
    }

    public function label(): string
    {
        return 'Brave Images';
    }

    public function categories(): array
    {
        return ['images'];
    }

    public function pageSize(): int
    {
        return 20;
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'source' => 'web',
        ];

        $safe = match ($query->safesearch) {
            1 => 'moderate',
            2 => 'strict',
            default => 'off',
        };
        $params['safesearch'] = $safe;

        return HttpRequest::get(
            'https://search.brave.com/images?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
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
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from Brave Images', ms: (int) $response->ms);
        }

        if ($response->status >= 400) {
            return new EngineOutcome(status: 'error', error: 'HTTP ' . $response->status . ' from Brave Images', ms: (int) $response->ms);
        }

        $xpath = $this->htmlXPath($response->body);
        $nodes = $xpath->query('//figure[.//img] | //a[contains(@class, "image") and .//img]');
        $results = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMNode) {
                    continue;
                }

                $imageNode = $xpath->query('.//img', $node)->item(0);
                if (!$imageNode instanceof \DOMElement) {
                    continue;
                }

                $src = $imageNode->getAttribute('src');
                if ($src === '') {
                    $src = $imageNode->getAttribute('data-src');
                }
                if ($src === '' || !preg_match('~^https?://~i', $src)) {
                    continue;
                }

                $link = $node instanceof \DOMElement && $node->tagName === 'a'
                    ? $node
                    : $xpath->query('.//a[@href]', $node)->item(0);
                $page = $link instanceof \DOMElement ? $link->getAttribute('href') : '';
                if ($page === '' || !preg_match('~^https?://~i', $page)) {
                    $page = $src;
                }

                $title = $this->clean($imageNode->getAttribute('alt'));
                if ($title === '') {
                    $title = $this->host($page);
                }

                $results[] = new ImageResult(
                    $title,
                    $src,
                    $src,
                    $page,
                    null,
                    null,
                    $this->id(),
                    count($results) + 1
                );
            }
        }

        return new EngineOutcome(
            images: $results,
            status: $results === [] ? 'empty' : 'ok',
            ms: (int) $response->ms
        );
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : 'image';
    }
}
