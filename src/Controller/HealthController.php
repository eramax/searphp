<?php

declare(strict_types=1);

namespace SearPHP\Controller;

use SearPHP\Cache\CacheInterface;
use SearPHP\Config;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Http\Request;
use SearPHP\Http\Response;

final class HealthController
{
    public function __construct(
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly EngineRegistry $engines,
    ) {
    }

    public function index(Request $request): Response
    {
        $cacheOk = $this->cache->health();

        return Response::json([
            'status' => $cacheOk ? 'ok' : 'degraded',
            'name' => (string) $this->config->get('app.name', 'SearPHP'),
            'version' => (string) $this->config->get('app.version', '1.0.0'),
            'php' => PHP_VERSION,
            'cache' => [
                'driver' => $this->cache->driver(),
                'ok' => $cacheOk,
            ],
            'engines' => [
                'enabled' => count($this->engines->enabledIds()),
                'web' => count($this->engines->defaultWebIds()),
                'images' => count($this->engines->defaultImageIds()),
            ],
            'time' => gmdate('c'),
        ]);
    }
}
