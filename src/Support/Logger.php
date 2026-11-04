<?php

declare(strict_types=1);

namespace SearPHP\Support;

use SearPHP\Config;

final class Logger
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->config->get('logging.enabled', true)) {
            return;
        }

        $safeContext = $this->redact($context);
        $line = json_encode([
            'time' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $safeContext,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($line === false) {
            return;
        }

        $file = (string) $this->config->get('logging.file', '');
        if ($file !== '') {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $maxBytes = (int) $this->config->get('logging.max_bytes', 5_242_880);
                if ($maxBytes > 0 && is_file($file) && filesize($file) > $maxBytes) {
                    @rename($file, $file . '.1');
                }
                @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
                return;
            }
        }

        error_log($line);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->redact($value);
                continue;
            }

            if (preg_match('/key|authorization|cookie|token|secret|password/i', (string) $key) === 1) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
