<?php

declare(strict_types=1);

use SearPHP\Support\Env;

$root = dirname(__DIR__);

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'SearPHP'),
        'version' => '1.0.0',
        'debug' => Env::bool('APP_DEBUG', false),
        'url' => Env::get('APP_URL', 'http://localhost:8080'),
        'base_path' => Env::get('APP_BASE_PATH'),
        'root' => $root,
    ],

    'http' => [
        'timeout_ms' => Env::int('HTTP_TIMEOUT_MS', 1800),
        'budget_ms' => Env::int('HTTP_BUDGET_MS', 2600),
        'connect_timeout_ms' => Env::int('HTTP_CONNECT_TIMEOUT_MS', 800),
        'max_bytes' => Env::int('HTTP_MAX_BYTES', 2_097_152),
        'max_redirects' => Env::int('HTTP_MAX_REDIRECTS', 3),
        'user_agent' => Env::get(
            'HTTP_USER_AGENT',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15'
        ),
        'ddg_user_agent' => Env::get(
            'DDG_USER_AGENT',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15'
        ),
        'ddg_fallback_user_agent' => Env::get(
            'DDG_FALLBACK_USER_AGENT',
            'Lynx/2.8.9rel.1 libwww-FM/2.14 SSL-MM/1.4.1'
        ),
        'browser_user_agent' => Env::get(
            'BROWSER_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ),
    ],

    'cors' => [
        'origins' => Env::get('CORS_ORIGINS', '*'),
        'max_age' => Env::int('CORS_MAX_AGE', 86_400),
    ],

    'security' => [
        'rate_limit' => Env::int('RATE_LIMIT', 120),
        'rate_window' => Env::int('RATE_WINDOW', 60),
        'trust_proxy' => Env::bool('TRUST_PROXY', false),
    ],

    // PHP file cache. No database is used.
    'cache' => [
        'driver' => Env::get('CACHE_DRIVER', 'file'),
        'ttl' => Env::int('CACHE_TTL', 600),
        'image_ttl' => Env::int('CACHE_IMAGE_TTL', 1800),
        'dir' => Env::get('CACHE_DIR', $root . '/var/cache'),
    ],

    'engines' => [
        'duckduckgo' => [
            'enabled' => Env::bool('ENGINE_DDG', true),
            'weight' => 1.0,
            'timeout_ms' => Env::int('ENGINE_DDG_TIMEOUT', 1800),
            'rate_per_min' => Env::int('ENGINE_DDG_RATE', 40),
        ],
        'duckduckgo_lite' => [
            'enabled' => Env::bool('ENGINE_DDG_LITE', true),
            'weight' => 0.9,
            'timeout_ms' => Env::int('ENGINE_DDG_LITE_TIMEOUT', 1800),
            'rate_per_min' => Env::int('ENGINE_DDG_LITE_RATE', 40),
        ],
        'bing' => [
            'enabled' => Env::bool('ENGINE_BING', true),
            'weight' => 0.9,
            'timeout_ms' => Env::int('ENGINE_BING_TIMEOUT', 1800),
            'rate_per_min' => Env::int('ENGINE_BING_RATE', 60),
        ],
        'brave' => [
            'enabled' => Env::bool('ENGINE_BRAVE', true),
            'weight' => 0.95,
            'timeout_ms' => Env::int('ENGINE_BRAVE_TIMEOUT', 2200),
            'rate_per_min' => Env::int('ENGINE_BRAVE_RATE', 20),
        ],
        'wikipedia' => [
            'enabled' => Env::bool('ENGINE_WIKIPEDIA', true),
            'weight' => 0.7,
            'timeout_ms' => Env::int('ENGINE_WIKIPEDIA_TIMEOUT', 1500),
            'rate_per_min' => Env::int('ENGINE_WIKIPEDIA_RATE', 120),
        ],
        'ddg_instant' => [
            'enabled' => Env::bool('ENGINE_DDG_INSTANT', true),
            'weight' => 0.35,
            'timeout_ms' => Env::int('ENGINE_DDG_INSTANT_TIMEOUT', 1500),
            'rate_per_min' => Env::int('ENGINE_DDG_INSTANT_RATE', 60),
        ],
        'duckduckgo_images' => [
            'enabled' => Env::bool('ENGINE_DDG_IMAGES', true),
            'weight' => 1.0,
            'timeout_ms' => Env::int('ENGINE_DDG_IMAGES_TIMEOUT', 1800),
            'rate_per_min' => Env::int('ENGINE_DDG_IMAGES_RATE', 30),
        ],
        'bing_images' => [
            'enabled' => Env::bool('ENGINE_BING_IMAGES', true),
            'weight' => 0.9,
            'timeout_ms' => Env::int('ENGINE_BING_IMAGES_TIMEOUT', 1800),
            'rate_per_min' => Env::int('ENGINE_BING_IMAGES_RATE', 40),
        ],
        'brave_images' => [
            'enabled' => Env::bool('ENGINE_BRAVE_IMAGES', false),
            'weight' => 0.8,
            'timeout_ms' => Env::int('ENGINE_BRAVE_IMAGES_TIMEOUT', 2200),
            'rate_per_min' => Env::int('ENGINE_BRAVE_IMAGES_RATE', 15),
        ],
    ],

    'engine_defaults' => [
        'web' => ['duckduckgo', 'bing', 'brave', 'wikipedia', 'ddg_instant'],
        'images' => ['duckduckgo_images', 'bing_images', 'brave_images'],
    ],

    'mcp' => [
        'enabled' => Env::bool('MCP_ENABLED', true),
    ],

    'logging' => [
        'enabled' => Env::bool('LOG_ENABLED', true),
        'queries' => Env::bool('LOG_QUERIES', false),
        'file' => Env::get('LOG_FILE', $root . '/var/log/app.log'),
        'max_bytes' => Env::int('LOG_MAX_BYTES', 5_242_880),
    ],
];
