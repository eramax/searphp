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
}
