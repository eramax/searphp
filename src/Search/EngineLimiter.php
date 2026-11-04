<?php

declare(strict_types=1);

namespace SearPHP\Search;

use SearPHP\Cache\CacheInterface;
use SearPHP\Config;

final class EngineLimiter
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config,
    ) {
    }

    public function allow(string $engine): bool
    {
        $limit = (int) $this->config->get('engines.' . $engine . '.rate_per_min', 60);
        if ($limit <= 0) {
            return true;
        }

        $bucket = 'eng:rate:' . $engine . ':' . intdiv(time(), 60);
        $hits = $this->cache->incr($bucket, 120);

        return $hits <= $limit;
    }
}
