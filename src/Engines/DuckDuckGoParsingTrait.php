<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use DOMNode;
use SearPHP\Search\Result;
use SearPHP\Search\UrlCanonicalizer;

trait DuckDuckGoParsingTrait
{
    /**
     * @return list<Result>
     */
    protected function parseDuckDuckGoWeb(string $html, string $engineId): array
    {
        $xpath = $this->htmlXPath($html);
        $nodes = $xpath->query('//div[contains(@class, "result__body")]');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//div[contains(@class, "web-result")]');
        }

        $results = [];
        if ($nodes === false) {
            return $results;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMNode) {
                continue;
            }

            $ad = $xpath->query('ancestor::div[contains(@class, "result--ad")]', $node)->item(0);
            if ($ad instanceof DOMNode) {
                continue;
            }

            $link = $xpath->query('.//a[contains(@class, "result__a")]', $node)->item(0);
            if (!$link instanceof DOMNode) {
                continue;
            }

            $url = UrlCanonicalizer::fromRedirect($link->getAttribute('href'));
            if ($url === null) {
                continue;
            }

            $snippetNode = $xpath->query('.//a[contains(@class, "result__snippet")]', $node)->item(0);
            if (!$snippetNode instanceof DOMNode) {
                $snippetNode = $xpath->query('.//div[contains(@class, "result__snippet")]', $node)->item(0);
            }

            $results[] = new Result(
                $this->clean($link->textContent),
                $url,
                $snippetNode instanceof DOMNode ? $this->text($snippetNode) : '',
                $engineId,
                count($results) + 1
            );
        }

        return $results;
    }

    /**
     * @return list<Result>
     */
    protected function parseDuckDuckGoLite(string $html, string $engineId): array
    {
        $xpath = $this->htmlXPath($html);
        $links = $xpath->query('//a[contains(@class, "result-link")]');
        if ($links === false) {
            return [];
        }

        $results = [];
        foreach ($links as $link) {
            if (!$link instanceof DOMNode) {
                continue;
            }

            $url = UrlCanonicalizer::fromRedirect($link->getAttribute('href'));
            if ($url === null) {
                continue;
            }

            $row = $link->parentNode?->parentNode;
            $snippet = '';
            if ($row instanceof DOMNode) {
                $snippetNode = $xpath->query(
                    'following-sibling::tr[1]//td[contains(@class, "result-snippet")]',
                    $row
                )->item(0);
                if ($snippetNode instanceof DOMNode) {
                    $snippet = $this->text($snippetNode);
                }
            }

            $results[] = new Result(
                $this->clean($link->textContent),
                $url,
                $snippet,
                $engineId,
                count($results) + 1
            );
        }

        return $results;
    }
}
