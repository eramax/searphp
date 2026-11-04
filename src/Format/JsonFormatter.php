<?php

declare(strict_types=1);

namespace SearPHP\Format;

use SearPHP\Search\Query;
use SearPHP\Search\SearchResultSet;

final class JsonFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function format(SearchResultSet $set, Query $query): array
    {
        $results = [];
        foreach ($set->results as $index => $result) {
            $item = $result->toArray();
            $item['position'] = $index + 1;
            $results[] = $item;
        }

        $images = [];
        foreach ($set->images as $index => $image) {
            $item = $image->toArray();
            $item['position'] = $index + 1;
            $images[] = $item;
        }

        $hasContent = $results !== [] || $images !== [] || $set->answers !== [];

        return [
            'query' => $set->query,
            'ok' => !$set->partial || $hasContent,
            'count' => count($results),
            'image_count' => count($images),
            'took_ms' => $set->tookMs,
            'cached' => $set->cached,
            'partial' => $set->partial,
            'results' => $results,
            'images' => $images,
            'answers' => $set->answers,
            'suggestions' => $set->suggestions,
            'meta' => [
                'page' => $set->page,
                'limit' => $set->limit,
                'engines' => $set->engineStatus,
                'pagination' => [
                    'page' => $set->page,
                    'limit' => $set->limit,
                    'has_more' => $set->hasMore,
                    'max_page' => $set->maxPage,
                    'next_page' => ($set->hasMore && $set->page < $set->maxPage) ? $set->page + 1 : null,
                ],
            ],
        ];
    }
}
