<?php

declare(strict_types=1);

namespace SearPHP\Search;

final class Ranker
{
    /**
     * @param array<string, EngineOutcome> $outcomes
     * @param array<string, float> $weights
     * @return list<Result>
     */
    public function mergeResults(array $outcomes, Query $query, array $weights): array
    {
        $merged = [];

        foreach ($outcomes as $engineId => $outcome) {
            $weight = $weights[$engineId] ?? 1.0;

            foreach ($outcome->results as $index => $result) {
                $url = UrlCanonicalizer::normalize($result->url);
                if ($url === null) {
                    continue;
                }

                $result->url = $url;
                $result->rank = $index + 1;
                $result->score = $weight / (60 + $result->rank);

                if ($result->title === '') {
                    $host = parse_url($url, PHP_URL_HOST);
                    $result->title = is_string($host) ? $host : $url;
                }

                $key = UrlCanonicalizer::key($url);
                if ($key === null) {
                    continue;
                }

                if (isset($merged[$key])) {
                    $merged[$key]->merge($result);
                } else {
                    $merged[$key] = $result;
                }
            }
        }

        $terms = $this->terms($query->q);
        foreach ($merged as $result) {
            $title = $this->lower($result->title);

            if ($terms !== []) {
                $hits = 0;
                foreach ($terms as $term) {
                    if (str_contains($title, $term)) {
                        $hits++;
                    }
                }
                $result->score += 0.02 * ($hits / count($terms));
            }

            if (count($result->engines) > 1) {
                $result->score += 0.01 * (count($result->engines) - 1);
            }

            if (str_starts_with($result->url, 'https://')) {
                $result->score += 0.001;
            }
        }

        $results = array_values($merged);
        usort($results, static function (Result $a, Result $b): int {
            return $b->score <=> $a->score
                ?: count($b->engines) <=> count($a->engines)
                ?: strcmp($a->title, $b->title);
        });

        return array_slice($results, 0, $query->limit);
    }

    /**
     * @param array<string, EngineOutcome> $outcomes
     * @param array<string, float> $weights
     * @return list<ImageResult>
     */
    public function mergeImages(array $outcomes, Query $query, array $weights): array
    {
        $merged = [];

        foreach ($outcomes as $engineId => $outcome) {
            $weight = $weights[$engineId] ?? 1.0;

            foreach ($outcome->images as $index => $image) {
                $imageUrl = UrlCanonicalizer::normalize($image->imageUrl);
                if ($imageUrl === null) {
                    continue;
                }

                $image->imageUrl = $imageUrl;
                $image->rank = $index + 1;
                $image->score = $weight / (60 + $image->rank);

                $key = UrlCanonicalizer::key($imageUrl);
                if ($key === null) {
                    continue;
                }

                if (isset($merged[$key])) {
                    $merged[$key]->merge($image);
                } else {
                    $merged[$key] = $image;
                }
            }
        }

        $images = array_values($merged);
        usort($images, static function (ImageResult $a, ImageResult $b): int {
            return $b->score <=> $a->score ?: $a->rank <=> $b->rank;
        });

        return array_slice($images, 0, $query->limit);
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $this->lower($query)) ?: [];

        return array_values(array_filter(
            array_unique($parts),
            static fn (string $term): bool => mb_strlen($term, 'UTF-8') > 2
        ));
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
