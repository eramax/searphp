<?php

declare(strict_types=1);

namespace SearPHP\Format;

use SearPHP\Search\Query;
use SearPHP\Search\SearchResultSet;

/**
 * SearXNG-compatible JSON, so existing agent clients can point at SearPHP
 * without changing their response parser.
 */
final class SearxFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function format(SearchResultSet $set, Query $query): array
    {
        $maxScore = 0.0;
        foreach ($set->results as $result) {
            $maxScore = max($maxScore, $result->score);
        }

        $results = [];
        foreach ($set->results as $result) {
            $results[] = [
                'url' => $result->url,
                'title' => $result->title,
                'content' => $result->snippet,
                'engine' => $result->engine,
                'engines' => $result->engines,
                'score' => $maxScore > 0 ? round($result->score / $maxScore, 4) : 0.0,
                'category' => 'general',
                'publishedDate' => $result->publishedAt,
                'thumbnail' => $result->thumbnail,
                'img_src' => null,
                'priority' => '',
            ];
        }

        if ($results === [] && $set->images !== []) {
            foreach ($set->images as $image) {
                $results[] = [
                    'url' => $image->pageUrl,
                    'title' => $image->title,
                    'content' => '',
                    'engine' => $image->engine,
                    'engines' => $image->engines,
                    'score' => 0.0,
                    'category' => 'images',
                    'publishedDate' => null,
                    'thumbnail' => $image->thumbnailUrl,
                    'img_src' => $image->imageUrl,
                    'template' => 'images.html',
                    'priority' => '',
                ];
            }
        }

        $answers = [];
        foreach ($set->answers as $answer) {
            $answers[] = [
                'answer' => (string) ($answer['text'] ?? ''),
                'url' => (string) ($answer['url'] ?? ''),
            ];
        }

        $unresponsive = [];
        foreach ($set->engineStatus as $engine => $status) {
            if (in_array($status['status'], ['ok', 'empty', 'disabled'], true)) {
                continue;
            }
            $unresponsive[] = [$engine, $status['error'] ?? $status['status']];
        }

        return [
            'query' => $set->query,
            'number_of_results' => count($results),
            'results' => $results,
            'answers' => $answers,
            'corrections' => [],
            'infoboxes' => [],
            'suggestions' => $set->suggestions,
            'unresponsive_engines' => $unresponsive,
            'images' => array_map(static fn ($image): array => $image->toArray(), $set->images),
            'meta' => [
                'took_ms' => $set->tookMs,
                'cached' => $set->cached,
                'partial' => $set->partial,
            ],
        ];
    }
}
