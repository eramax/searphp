<?php

declare(strict_types=1);

namespace SearPHP\Cache;

final class FileCache implements CacheInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = @gzuncompress($raw);
        if ($decoded === false) {
            @unlink($file);
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['e'], $payload['v'])) {
            @unlink($file);
            return null;
        }

        if ((int) $payload['e'] < time()) {
            @unlink($file);
            return null;
        }

        return $payload['v'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->maybeCleanup();
        $file = $this->file($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode(
            ['e' => time() + $ttl, 'v' => $value],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $compressed = gzcompress($payload, 6);
        $temp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($temp, $compressed, LOCK_EX) !== false) {
            @rename($temp, $file);
        } else {
            @unlink($temp);
        }
    }

    public function delete(string $key): void
    {
        @unlink($this->file($key));
    }

    public function incr(string $key, int $ttl): int
    {
        $this->maybeCleanup();
        $file = $this->file($key);
        $lockFile = $file . '.lock';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lock = @fopen($lockFile, 'c');
        if ($lock === false) {
            return 1;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return 1;
            }

            $current = 0;
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                $decoded = is_string($raw) ? @gzuncompress($raw) : false;
                $payload = is_string($decoded) ? json_decode($decoded, true) : null;
                if (is_array($payload) && isset($payload['e'], $payload['v']) && (int) $payload['e'] >= time()) {
                    $current = (int) $payload['v'];
                }
            }

            $next = $current + 1;
            $payload = json_encode(
                ['e' => time() + $ttl, 'v' => $next],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
            );
            @file_put_contents($file, gzcompress($payload, 6), LOCK_EX);

            return $next;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function health(): bool
    {
        return is_dir($this->directory) && is_writable($this->directory);
    }

    public function driver(): string
    {
        return 'file';
    }

    private function file(string $key): string
    {
        $hash = hash('sha256', $key);

        return $this->directory . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    /**
     * Remove old cache, temp, and lock files. Called rarely so it does not add
     * per-request filesystem scans. TTLs are at most 30 minutes, so anything
     * older than a day is safe to remove.
     */
    private function maybeCleanup(): void
    {
        if (random_int(1, 100) !== 1 || !is_dir($this->directory)) {
            return;
        }

        $cutoff = time() - 86_400;
        $count = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (++$count > 250) {
                    break;
                }

                $name = $file->getFilename();
                if (!str_ends_with($name, '.cache') && !str_ends_with($name, '.tmp') && !str_ends_with($name, '.lock')) {
                    continue;
                }

                if ($file->getMTime() < $cutoff) {
                    @unlink($file->getPathname());
                }
            }
        } catch (\Throwable) {
            // Cleanup is best-effort; never fail a request because of it.
        }
    }
}
