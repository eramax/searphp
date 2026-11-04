<?php

declare(strict_types=1);

namespace SearPHP\Cache;

final class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
    }

    public function delete(string $key): void
    {
    }

    public function incr(string $key, int $ttl): int
    {
        return 1;
    }

    public function health(): bool
    {
        return true;
    }

    public function driver(): string
    {
        return 'null';
    }
}
