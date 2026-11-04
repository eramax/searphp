<?php

declare(strict_types=1);

namespace SearPHP\Http;

use SearPHP\Config;

final class Cors
{
    private const ALLOW_HEADERS = 'Content-Type, Accept, Authorization, X-API-Key, X-Requested-With, MCP-Protocol-Version, Mcp-Session-Id';
    private const EXPOSE_HEADERS = 'X-Request-Id, X-Search-Time-Ms, X-Cache, X-Engines, X-Partial, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After';
    private const ALLOW_METHODS = 'GET, POST, OPTIONS';

    public function __construct(private readonly Config $config)
    {
    }

    public function preflight(Request $request): Response
    {
        return $this->apply(Response::noContent(), $request);
    }

    public function apply(Response $response, Request $request): Response
    {
        $origin = $request->origin();
        $allowed = $this->resolveOrigin($origin);

        if ($allowed === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowed)
            ->withHeader('Access-Control-Allow-Methods', self::ALLOW_METHODS)
            ->withHeader('Access-Control-Allow-Headers', self::ALLOW_HEADERS)
            ->withHeader('Access-Control-Expose-Headers', self::EXPOSE_HEADERS)
            ->withHeader('Access-Control-Max-Age', (string) $this->config->get('cors.max_age', 86400));

        if ($allowed !== '*') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    private function resolveOrigin(?string $origin): ?string
    {
        $configured = (string) $this->config->get('cors.origins', '*');

        if ($configured === '*' || $configured === '') {
            return '*';
        }

        if ($origin === null) {
            return null;
        }

        $allowed = array_map(
            static fn (string $item): string => rtrim(trim($item), '/'),
            explode(',', $configured)
        );

        foreach ($allowed as $candidate) {
            if ($candidate === '*' || strcasecmp($candidate, $origin) === 0) {
                return $origin;
            }
        }

        return null;
    }
}
