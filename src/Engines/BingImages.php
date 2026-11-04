<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use DOMNode;
use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\ImageResult;
use SearPHP\Search\Query;

final class BingImages extends AbstractEngine
{
    public function id(): string
    {
        return 'bing_images';
    }

    public function label(): string
    {
        return 'Bing Images';
    }

    public function categories(): array
    {
        return ['images'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'count' => '35',
            'first' => (string) (($query->page - 1) * 35 + 1),
            'mmasync' => '1',
            'setlang' => substr($query->lang, 0, 2),
        ];

        $safe = match ($query->safesearch) {
            1 => 'moderate',
            2 => 'strict',
            default => 'off',
        };
        $params['adlt'] = $safe;

        return HttpRequest::get(
            'https://www.bing.com/images/async?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
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
            return new EngineOutcome(status: 'rate_limited', error: 'HTTP 429 from Bing Images', ms: (int) $response->ms);
        }

        if ($response->status >= 400) {
            return new EngineOutcome(status: 'error', error: 'HTTP ' . $response->status . ' from Bing Images', ms: (int) $response->ms);
        }

        $xpath = $this->htmlXPath($response->body);
        $nodes = $xpath->query('//a[contains(@class, "iusc")]');
        $results = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMNode) {
                    continue;
                }

                $raw = $node->getAttribute('m');
                if ($raw === '') {
                    continue;
                }

                $data = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if (!is_array($data)) {
                    continue;
                }

                $image = trim((string) ($data['murl'] ?? ''));
                if ($image === '' || !preg_match('~^https?://~i', $image)) {
                    continue;
                }

                $page = trim((string) ($data['purl'] ?? ''));
                if ($page === '' || !preg_match('~^https?://~i', $page)) {
                    $page = $image;
                }

                $title = trim((string) ($data['t'] ?? ''));

                $results[] = new ImageResult(
                    $title !== '' ? $this->clean($title) : $this->host($page),
                    $image,
                    trim((string) ($data['turl'] ?? '')) ?: $image,
                    $page,
                    isset($data['w']) ? (int) $data['w'] : null,
                    isset($data['h']) ? (int) $data['h'] : null,
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
