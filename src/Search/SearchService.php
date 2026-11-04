<?php

declare(strict_types=1);

namespace SearPHP\Search;

use Throwable;
use SearPHP\Cache\CacheInterface;
use SearPHP\Config;
use SearPHP\Engines\EngineInterface;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Http\HttpClient;
use SearPHP\Http\HttpResponse;
use SearPHP\Support\Logger;

final class SearchService
{
    private readonly Ranker $ranker;

    public function __construct(
        private readonly Config $config,
        private readonly EngineRegistry $engines,
        private readonly HttpClient $http,
        private readonly CacheInterface $cache,
        private readonly EngineLimiter $limiter,
        private readonly EngineStats $stats,
        private readonly Logger $logger,
    ) {
        $this->ranker = new Ranker();
    }

    public function search(Query $query): SearchResultSet
    {
        $started = microtime(true);
        $kind = $query->includeImages ? 'web+images' : 'web';
        $cacheKey = $this->cacheKey($query, $kind);

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return SearchResultSet::fromArray($cached, true)->withTiming(
                (int) ((microtime(true) - $started) * 1000),
                true
            );
        }

        $webIds = $this->resolveWebIds($query);
        $outcomes = $this->run($webIds, $query);
        $results = $this->ranker->mergeResults($outcomes, $query, $this->engines->weights($webIds));

        $images = [];
        if ($query->includeImages) {
            $imageIds = $this->resolveImageIds($query);
            $imageOutcomes = $this->run($imageIds, $query);
            $images = $this->ranker->mergeImages($imageOutcomes, $query, $this->engines->weights($imageIds));
            $outcomes += $imageOutcomes;
        }

        $engineStatus = $this->engineStatus($outcomes);
        $partial = $this->isPartial($engineStatus);
        $tookMs = (int) ((microtime(true) - $started) * 1000);

        $set = new SearchResultSet(
            query: $query->q,
            results: $results,
            images: $images,
            answers: $this->collectAnswers($outcomes),
            suggestions: $this->collectSuggestions($outcomes),
            engineStatus: $engineStatus,
            tookMs: $tookMs,
            cached: false,
            partial: $partial,
            page: $query->page,
            limit: $query->limit,
        );

        $ttl = $this->cacheTtl($query, $partial);
        $this->cache->set($cacheKey, $set->toArray(), $ttl);
        $this->logSearch($query, $set);

        return $set;
    }

    public function images(Query $query): SearchResultSet
    {
        $started = microtime(true);
        $cacheKey = $this->cacheKey($query, 'images');

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return SearchResultSet::fromArray($cached, true)->withTiming(
                (int) ((microtime(true) - $started) * 1000),
                true
            );
        }

        $imageIds = $this->resolveImageIds($query);
        $outcomes = $this->run($imageIds, $query);
        $images = $this->ranker->mergeImages($outcomes, $query, $this->engines->weights($imageIds));
        $engineStatus = $this->engineStatus($outcomes);
        $partial = $this->isPartial($engineStatus);
        $tookMs = (int) ((microtime(true) - $started) * 1000);

        $set = new SearchResultSet(
            query: $query->q,
            images: $images,
            engineStatus: $engineStatus,
            tookMs: $tookMs,
            cached: false,
            partial: $partial,
            page: $query->page,
            limit: $query->limit,
        );

        $this->cache->set($cacheKey, $set->toArray(), $this->cacheTtl($query, $partial));
        $this->logSearch($query, $set);

        return $set;
    }

    /**
     * @param list<string> $engineIds
     * @return array<string, EngineOutcome>
     */
    private function run(array $engineIds, Query $query): array
    {
        $outcomes = [];
        $pending = [];

        foreach ($engineIds as $id) {
            $engine = $this->engines->get($id);
            if ($engine === null || !$this->engines->isEnabled($id)) {
                $outcomes[$id] = new EngineOutcome(status: 'disabled', error: 'Engine is disabled');
                continue;
            }

            try {
                $request = $engine->request($query);
            } catch (Throwable $exception) {
                $outcomes[$id] = new EngineOutcome(
                    status: 'error',
                    error: $exception->getMessage()
                );
                continue;
            }

            if ($request === null) {
                $outcomes[$id] = new EngineOutcome(status: 'disabled', error: 'Engine is not configured');
                continue;
            }

            if ($this->stats->shouldSkip($id)) {
                $outcomes[$id] = new EngineOutcome(status: 'circuit_open', error: 'Engine circuit is open after repeated failures');
                continue;
            }

            if (!$this->limiter->allow($id)) {
                $outcomes[$id] = new EngineOutcome(status: 'rate_limited', error: 'Local engine rate limit reached');
                continue;
            }

            $timeout = min(
                $request->timeoutMs,
                (int) $this->config->get('engines.' . $id . '.timeout_ms', $request->timeoutMs)
            );
            $request = $request->withTimeout(max(200, $timeout));

            $pending[$id] = [
                'engine' => $engine,
                'request' => $request,
                'previous' => null,
            ];
        }

        if ($pending === []) {
            return $outcomes;
        }

        $maxTimeout = 0;
        foreach ($pending as $item) {
            $maxTimeout = max($maxTimeout, $item['request']->timeoutMs);
        }

        $budget = max(
            (int) $this->config->get('http.budget_ms', 2600),
            $maxTimeout + 750
        );
        $deadline = microtime(true) + ($budget / 1000);

        for ($round = 0; $round < 2 && $pending !== []; $round++) {
            $batch = [];
            foreach ($pending as $id => $item) {
                $batch[$id] = $item['request'];
            }

            $responses = $this->http->fetchAll($batch, $deadline);
            $next = [];

            foreach ($pending as $id => $item) {
                /** @var EngineInterface $engine */
                $engine = $item['engine'];
                $request = $item['request'];
                $response = $responses[$id] ?? HttpResponse::error($request->url, 'No response from engine');

                try {
                    $outcome = $engine->parse($response, $query);
                } catch (Throwable $exception) {
                    $outcome = new EngineOutcome(
                        status: 'error',
                        error: $exception->getMessage(),
                        ms: (int) $response->ms
                    );
                }

                $outcome = new EngineOutcome(
                    results: $outcome->results,
                    images: $outcome->images,
                    answers: $outcome->answers,
                    suggestions: $outcome->suggestions,
                    followUp: $outcome->followUp,
                    status: $outcome->status,
                    error: $outcome->error,
                    ms: max((int) $response->ms, $outcome->ms)
                );

                if ($outcome->followUp !== null && $round < 1 && microtime(true) < $deadline) {
                    $followTimeout = min(
                        $outcome->followUp->timeoutMs,
                        (int) $this->config->get('engines.' . $id . '.timeout_ms', $outcome->followUp->timeoutMs)
                    );
                    $next[$id] = [
                        'engine' => $engine,
                        'request' => $outcome->followUp->withTimeout(max(200, $followTimeout)),
                        'previous' => $this->combine($item['previous'], $outcome),
                    ];
                    continue;
                }

                if ($outcome->followUp !== null) {
                    $outcome = new EngineOutcome(
                        results: $outcome->results,
                        images: $outcome->images,
                        answers: $outcome->answers,
                        suggestions: $outcome->suggestions,
                        status: 'timeout',
                        error: 'Follow-up request could not be completed within the search budget',
                        ms: $outcome->ms,
                    );
                }

                $final = $this->combine($item['previous'], $outcome);
                $outcomes[$id] = $final;
                $this->stats->record($id, $final->status, $final->ms, $final->error);
            }

            $pending = $next;
        }

        foreach ($pending as $id => $item) {
            $final = $this->combine(
                $item['previous'],
                new EngineOutcome(status: 'timeout', error: 'Total search deadline exceeded')
            );
            $outcomes[$id] = $final;
            $this->stats->record($id, $final->status, $final->ms, $final->error);
        }

        return $outcomes;
    }

    private function combine(?EngineOutcome $previous, EngineOutcome $next): EngineOutcome
    {
        if ($previous === null) {
            return new EngineOutcome(
                results: $next->results,
                images: $next->images,
                answers: $next->answers,
                suggestions: $next->suggestions,
                status: $next->status,
                error: $next->status === 'ok' ? null : $next->error,
                ms: $next->ms,
            );
        }

        $hasPreviousContent = $previous->results !== [] || $previous->images !== [] || $previous->answers !== [];
        $status = ($next->status === 'empty' && $hasPreviousContent) ? 'ok' : $next->status;
        $error = $status === 'ok' ? null : ($next->error ?? $previous->error);

        return new EngineOutcome(
            results: array_merge($previous->results, $next->results),
            images: array_merge($previous->images, $next->images),
            answers: array_merge($previous->answers, $next->answers),
            suggestions: array_merge($previous->suggestions, $next->suggestions),
            status: $status,
            error: $error,
            ms: $previous->ms + $next->ms,
        );
    }

    /**
     * @return list<string>
     */
    private function resolveWebIds(Query $query): array
    {
        if ($query->engines === []) {
            return $this->engines->defaultWebIds();
        }

        $ids = [];
        foreach ($query->engines as $id) {
            $engine = $this->engines->get($id);
            if ($engine !== null && in_array('web', $engine->categories(), true)) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function resolveImageIds(Query $query): array
    {
        $map = [
            'duckduckgo' => 'duckduckgo_images',
            'ddg' => 'duckduckgo_images',
            'bing' => 'bing_images',
            'brave' => 'brave_images',
        ];

        $selected = $query->engines;
        if ($selected === []) {
            return $this->engines->defaultImageIds();
        }

        $ids = [];
        foreach ($selected as $id) {
            $mapped = $map[$id] ?? $id;
            $engine = $this->engines->get($mapped);
            if ($engine !== null && in_array('images', $engine->categories(), true)) {
                $ids[] = $mapped;
            }
        }

        return $ids === [] ? $this->engines->defaultImageIds() : array_values(array_unique($ids));
    }

    /**
     * @param array<string, EngineOutcome> $outcomes
     * @return array<string, array{status: string, ms: int, count: int, error?: string}>
     */
    private function engineStatus(array $outcomes): array
    {
        $status = [];
        foreach ($outcomes as $id => $outcome) {
            $entry = [
                'status' => $outcome->status,
                'ms' => $outcome->ms,
                'count' => $outcome->count(),
            ];
            if ($outcome->error !== null) {
                $entry['error'] = $outcome->error;
            }
            $status[$id] = $entry;
        }

        return $status;
    }

    /**
     * @param array<string, array{status: string, ms: int, count: int, error?: string}> $status
     */
    private function isPartial(array $status): bool
    {
        foreach ($status as $entry) {
            if (!in_array($entry['status'], ['ok', 'empty', 'disabled'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, EngineOutcome> $outcomes
     * @return list<array{text: string, source?: string, url?: string}>
     */
    private function collectAnswers(array $outcomes): array
    {
        $answers = [];
        $seen = [];

        foreach ($outcomes as $outcome) {
            foreach ($outcome->answers as $answer) {
                $text = trim((string) ($answer['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $key = mb_strtolower($text);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $answers[] = $answer;
                if (count($answers) >= 5) {
                    return $answers;
                }
            }
        }

        return $answers;
    }

    /**
     * @param array<string, EngineOutcome> $outcomes
     * @return list<string>
     */
    private function collectSuggestions(array $outcomes): array
    {
        $suggestions = [];
        foreach ($outcomes as $outcome) {
            foreach ($outcome->suggestions as $suggestion) {
                $suggestion = trim($suggestion);
                if ($suggestion !== '' && !in_array($suggestion, $suggestions, true)) {
                    $suggestions[] = $suggestion;
                }
                if (count($suggestions) >= 8) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }

    private function cacheKey(Query $query, string $kind): string
    {
        $engines = $query->engines;
        sort($engines);

        $data = [
            'v' => 1,
            'q' => $query->q,
            'engines' => $engines,
            'limit' => $query->limit,
            'page' => $query->page,
            'lang' => $query->lang,
            'safesearch' => $query->safesearch,
            'time' => $query->timeRange,
            'images' => $query->includeImages,
            'kind' => $kind,
        ];

        return 'srch:' . hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function cacheTtl(Query $query, bool $partial): int
    {
        if ($partial) {
            return 90;
        }

        if ($query->includeImages) {
            return max(60, (int) $this->config->get('cache.image_ttl', 1800));
        }

        return max(60, (int) $this->config->get('cache.ttl', 600));
    }

    private function logSearch(Query $query, SearchResultSet $set): void
    {
        $queryValue = (bool) $this->config->get('logging.queries', false)
            ? $query->q
            : hash('sha256', $query->q);

        $this->logger->info('search', [
            'query' => $queryValue,
            'engines' => array_keys($set->engineStatus),
            'results' => count($set->results),
            'images' => count($set->images),
            'took_ms' => $set->tookMs,
            'partial' => $set->partial,
            'cached' => $set->cached,
        ]);
    }
}
