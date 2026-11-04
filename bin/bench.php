<?php

declare(strict_types=1);

use SearPHP\Cache\CacheFactory;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Http\HttpClient;
use SearPHP\Search\EngineLimiter;
use SearPHP\Search\EngineStats;
use SearPHP\Search\Query;
use SearPHP\Search\SearchService;
use SearPHP\Support\Logger;

/** @var \SearPHP\Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$queries = array_slice($argv, 1);
if ($queries === []) {
    $queries = [
        'rust programming',
        'postgres index tuning',
        'php 8.5 new features',
        'best hiking boots',
        'linux kernel news',
    ];
}

$http = new HttpClient($config);
$cache = CacheFactory::create($config);
$engines = new EngineRegistry($config);
$stats = new EngineStats($cache);
$limiter = new EngineLimiter($cache, $config);
$logger = new Logger($config);
$search = new SearchService($config, $engines, $http, $cache, $limiter, $stats, $logger);

$cold = [];
$warm = [];

foreach ($queries as $text) {
    $query = new Query($text, limit: 10);

    $started = hrtime(true);
    $set = $search->search($query);
    $cold[] = (hrtime(true) - $started) / 1_000_000;

    // A different Query instance still hits the same cache key.
    $started = hrtime(true);
    $second = $search->search(new Query($text, limit: 10));
    $warm[] = (hrtime(true) - $started) / 1_000_000;

    printf(
        "%-28s cold=%7.1f ms  warm=%6.2f ms  results=%2d partial=%s cache=%s\n",
        mb_substr($text, 0, 28),
        end($cold),
        end($warm),
        count($set->results),
        $set->partial ? 'yes' : 'no',
        $second->cached ? 'hit' : 'miss'
    );
}

$percentile = static function (array $values, float $p): float {
    sort($values);
    if ($values === []) {
        return 0.0;
    }
    $index = (int) floor((count($values) - 1) * $p);

    return $values[$index];
};

printf(
    "\ncold p50=%.1f ms p95=%.1f ms | warm p50=%.2f ms p95=%.2f ms\n",
    $percentile($cold, 0.50),
    $percentile($cold, 0.95),
    $percentile($warm, 0.50),
    $percentile($warm, 0.95)
);
