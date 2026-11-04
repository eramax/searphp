<?php

declare(strict_types=1);

namespace SearPHP\Search;

use SearPHP\Cache\CacheInterface;

final class EngineStats
{
    private const TTL = 3600;
    private const CIRCUIT_THRESHOLD = 3;
    private const CIRCUIT_SECONDS = 60;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function record(string $engine, string $status, int $ms, ?string $error = null): void
    {
        $key = 'eng:stats:' . $engine;
        $stats = $this->cache->get($key);
        if (!is_array($stats)) {
            $stats = [
                'requests' => 0,
                'failures' => 0,
                'fail_streak' => 0,
                'last_status' => null,
                'last_ms' => 0,
                'last_error' => null,
                'last_failure_at' => 0,
                'updated_at' => 0,
            ];
        }

        $stats['requests'] = (int) ($stats['requests'] ?? 0) + 1;
        $stats['last_status'] = $status;
        $stats['last_ms'] = max(0, $ms);
        $stats['updated_at'] = time();

        if ($status === 'ok' || $status === 'empty') {
            $stats['fail_streak'] = 0;
            $stats['last_error'] = null;
        } else {
            $stats['failures'] = (int) ($stats['failures'] ?? 0) + 1;
            $stats['fail_streak'] = (int) ($stats['fail_streak'] ?? 0) + 1;
            $stats['last_error'] = $error !== null ? mb_substr($error, 0, 240) : $status;
            $stats['last_failure_at'] = time();
        }

        $this->cache->set($key, $stats, self::TTL);
    }

    public function shouldSkip(string $engine): bool
    {
        $stats = $this->cache->get('eng:stats:' . $engine);
        if (!is_array($stats)) {
            return false;
        }

        $failStreak = (int) ($stats['fail_streak'] ?? 0);
        $lastFailure = (int) ($stats['last_failure_at'] ?? 0);

        return $failStreak >= self::CIRCUIT_THRESHOLD
            && $lastFailure > 0
            && (time() - $lastFailure) < self::CIRCUIT_SECONDS;
    }

    /**
     * @param list<string> $engines
     * @return array<string, array<string, mixed>>
     */
    public function snapshot(array $engines): array
    {
        $snapshot = [];
        foreach ($engines as $engine) {
            $stats = $this->cache->get('eng:stats:' . $engine);
            $snapshot[$engine] = is_array($stats) ? $stats : [
                'requests' => 0,
                'failures' => 0,
                'fail_streak' => 0,
                'last_status' => null,
                'last_ms' => 0,
                'last_error' => null,
                'last_failure_at' => 0,
                'updated_at' => 0,
            ];
        }

        return $snapshot;
    }
}
