<?php

declare(strict_types=1);

use SearPHP\App;
use SearPHP\Cache\CacheFactory;
use SearPHP\Controller\EnginesController;
use SearPHP\Controller\HealthController;
use SearPHP\Controller\SearchController;

use SearPHP\Engines\EngineRegistry;
use SearPHP\Format\HtmlFormatter;
use SearPHP\Format\JsonFormatter;
use SearPHP\Format\MarkdownFormatter;
use SearPHP\Format\SearxFormatter;
use SearPHP\Http\Cors;
use SearPHP\Http\HttpClient;
use SearPHP\Http\Request;
use SearPHP\Mcp\McpServer;
use SearPHP\Search\EngineLimiter;
use SearPHP\Search\EngineStats;
use SearPHP\Search\SearchService;
use SearPHP\Support\Logger;

/** @var \SearPHP\Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', $config->get('app.debug', false) ? '1' : '0');
header_remove('X-Powered-By');

$cache = CacheFactory::create($config);
$logger = new Logger($config);
$http = new HttpClient($config);
$engines = new EngineRegistry($config);
$stats = new EngineStats($cache);
$limiter = new EngineLimiter($cache, $config);

$search = new SearchService($config, $engines, $http, $cache, $limiter, $stats, $logger);

$markdown = new MarkdownFormatter();
$json = new JsonFormatter();
$searx = new SearxFormatter();
$html = new HtmlFormatter($config);

$searchController = new SearchController($config, $search, $engines, $markdown, $json, $searx, $html);
$healthController = new HealthController($config, $cache, $engines);
$enginesController = new EnginesController($engines, $stats);
$mcp = new McpServer($config, $search, $engines, $markdown, $json);

$app = new App(
    $config,
    $searchController,
    $healthController,
    $enginesController,
    $mcp,
    new Cors($config),
    $cache,
    $logger
);

$app->handle(Request::fromGlobals($config->get('app.base_path')))->send();
