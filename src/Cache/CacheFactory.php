<?php

declare(strict_types=1);

namespace SearPHP\Cache;

use SearPHP\Config;

final class CacheFactory
{
    public static function create(Config $config): CacheInterface
    {
        $driver = strtolower((string) $config->get('cache.driver', 'file'));
        $directory = (string) $config->get('cache.dir', dirname(__DIR__, 2) . '/var/cache');

        if ($driver === 'null' || $driver === 'none') {
            return new NullCache();
        }

        return new FileCache($directory);
    }
}
