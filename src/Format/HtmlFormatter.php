<?php

declare(strict_types=1);

namespace SearPHP\Format;

use SearPHP\Config;
use SearPHP\Search\Query;
use SearPHP\Search\SearchResultSet;

final class HtmlFormatter
{
    public function __construct(private readonly Config $config)
    {
    }

    public function page(
        ?SearchResultSet $set = null,
        ?Query $query = null,
        string $basePath = '',
        bool $imagesMode = false,
    ): string {
        $appName = (string) $this->config->get('app.name', 'SearPHP');
        $version = (string) $this->config->get('app.version', '1.0.0');
        $basePath = rtrim($basePath, '/');
        $authRequired = (bool) $this->config->get('security.api_auth', true);
        $assetVersion = $this->assetVersion();
        $engines = $set !== null ? array_keys($set->engineStatus) : [];

        ob_start();

        try {
            require dirname(__DIR__, 2) . '/templates/search.php';
            $html = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return is_string($html) ? $html : '';
    }

    public function help(string $basePath = ''): string
    {
        $appName = (string) $this->config->get('app.name', 'SearPHP');
        $version = (string) $this->config->get('app.version', '1.0.0');
        $appUrl = rtrim((string) $this->config->get('app.url', 'http://localhost:8080'), '/');
        $basePath = rtrim($basePath, '/');
        $authRequired = (bool) $this->config->get('security.api_auth', true);
        $assetVersion = $this->assetVersion();

        ob_start();

        try {
            require dirname(__DIR__, 2) . '/templates/help.php';
            $html = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return is_string($html) ? $html : '';
    }

    public function keyRequired(string $basePath = ''): string
    {
        $appName = (string) $this->config->get('app.name', 'SearPHP');
        $version = (string) $this->config->get('app.version', '1.0.0');
        $basePath = rtrim($basePath, '/');
        $assetVersion = $this->assetVersion();

        ob_start();

        try {
            require dirname(__DIR__, 2) . '/templates/key-required.php';
            $html = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return is_string($html) ? $html : '';
    }

    private function assetVersion(): string
    {
        $file = dirname(__DIR__, 2) . '/public/app.js';
        $mtime = is_file($file) ? filemtime($file) : false;

        return (string) ($mtime !== false ? $mtime : $this->config->get('app.version', '1'));
    }
}
