<?php

declare(strict_types=1);

use SearPHP\Cache\CacheFactory;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Format\MarkdownFormatter;
use SearPHP\Http\HttpClient;
use SearPHP\Search\EngineLimiter;
use SearPHP\Search\EngineStats;
use SearPHP\Search\Query;
use SearPHP\Search\SearchService;
use SearPHP\Support\Logger;

/** @var \SearPHP\Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$text = $argv[1] ?? 'rust programming';
$format = $argv[2] ?? 'md';

$http = new HttpClient($config);
$cache = CacheFactory::create($config);
$engines = new EngineRegistry($config);
$stats = new EngineStats($cache);
$limiter = new EngineLimiter($cache, $config);
$logger = new Logger($config);
$search = new SearchService($config, $engines, $http, $cache, $limiter, $stats, $logger);

$query = new Query($text, limit: 10);
$started = microtime(true);
$set = $search->search($query);
$elapsed = (int) ((microtime(true) - $started) * 1000);

if ($format === 'json') {
    $json = new SearPHP\Format\JsonFormatter();
    echo json_encode($json->format($set, $query), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo PHP_EOL;
} else {
    $markdown = new MarkdownFormatter();
    echo $markdown->format($set, $query);
}

fwrite(STDERR, sprintf(
    "\n[took=%dms cached=%s partial=%s results=%d images=%d]\n",
    $elapsed,
    $set->cached ? 'yes' : 'no',
    $set->partial ? 'yes' : 'no',
    count($set->results),
    count($set->images)
));

foreach ($set->engineStatus as $engine => $status) {
    fwrite(STDERR, sprintf(
        "[%s] %s ms=%d count=%d%s\n",
        $engine,
        $status['status'],
        $status['ms'],
        $status['count'],
        isset($status['error']) ? ' error=' . $status['error'] : ''
    ));
}
