<?php

declare(strict_types=1);

namespace SearPHP\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): void;

    public function delete(string $key): void;

    public function incr(string $key, int $ttl): int;

    public function health(): bool;

    public function driver(): string;
}
