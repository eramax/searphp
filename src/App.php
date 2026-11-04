<?php

declare(strict_types=1);

namespace SearPHP;

use InvalidArgumentException;
use Throwable;
use SearPHP\Cache\CacheInterface;
use SearPHP\Controller\EnginesController;
use SearPHP\Controller\HealthController;
use SearPHP\Controller\SearchController;
use SearPHP\Http\Cors;
use SearPHP\Http\RateLimitException;
use SearPHP\Http\Request;
use SearPHP\Http\Response;
use SearPHP\Http\Router;
use SearPHP\Mcp\McpServer;
use SearPHP\Support\Logger;

final class App
{
    private readonly Router $router;

    /** @var array{limit: int, remaining: int, reset: int}|null */
    private ?array $rateMeta = null;

    public function __construct(
        private readonly Config $config,
        private readonly SearchController $searchController,
        private readonly HealthController $healthController,
        private readonly EnginesController $enginesController,
        private readonly McpServer $mcpServer,
        private readonly Cors $cors,
        private readonly CacheInterface $cache,
        private readonly Logger $logger,
    ) {
        $this->router = new Router();
        $this->routes();
    }

    public function handle(Request $request): Response
    {
        $started = microtime(true);
        $requestId = $request->requestId();

        try {
            if ($request->method === 'OPTIONS') {
                return $this->finalize($this->cors->preflight($request), $request, $requestId, $started);
            }

            if ($request->path !== '/health') {
                $this->rateLimit($request);
            }

            $response = $this->router->dispatch($request);
        } catch (RateLimitException $exception) {
            $response = Response::error(
                'rate_limited',
                'Too many requests. Retry later.',
                429,
                !$request->accepts('application/json')
            )->withHeaders([
                'Retry-After' => (string) $exception->retryAfter,
                'X-RateLimit-Limit' => (string) $exception->limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) $exception->reset,
            ]);
        } catch (InvalidArgumentException $exception) {
            $response = Response::error(
                'bad_request',
                $exception->getMessage(),
                400,
                !$request->accepts('application/json')
            );
        } catch (Throwable $exception) {
            $this->logger->error('unhandled_exception', [
                'request_id' => $requestId,
                'path' => $request->path,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $message = $this->config->get('app.debug', false)
                ? $exception->getMessage()
                : 'The search request failed.';

            $response = Response::error(
                'internal_error',
                $message,
                500,
                !$request->accepts('application/json')
            );
        }

        return $this->finalize($response, $request, $requestId, $started);
    }

    private function routes(): void
    {
        $this->router->get('/', fn (Request $request): Response => $this->searchController->home($request));
        $this->router->get('/search', fn (Request $request): Response => $this->searchController->search($request));
        $this->router->post('/search', fn (Request $request): Response => $this->searchController->search($request));
        $this->router->get('/images', fn (Request $request): Response => $this->searchController->images($request));
        $this->router->post('/images', fn (Request $request): Response => $this->searchController->images($request));

        // Explicit API aliases. They return the same MD/JSON/SearXNG formats.
        foreach (['/api/search', '/api/v1/search'] as $path) {
            $this->router->get($path, fn (Request $request): Response => $this->searchController->search($request));
            $this->router->post($path, fn (Request $request): Response => $this->searchController->search($request));
        }
        foreach (['/api/images', '/api/v1/images'] as $path) {
            $this->router->get($path, fn (Request $request): Response => $this->searchController->images($request));
            $this->router->post($path, fn (Request $request): Response => $this->searchController->images($request));
        }

        $this->router->get('/engines', fn (Request $request): Response => $this->enginesController->index($request));
        $this->router->get('/health', fn (Request $request): Response => $this->healthController->index($request));
        $this->router->get('/robots.txt', static fn (Request $request): Response => Response::text(
            "User-agent: *\nDisallow: /\n",
            200,
            'text/plain; charset=utf-8'
        ));
        $this->router->get('/openapi.yaml', function (Request $request): Response {
            $file = (string) $this->config->get('app.root', dirname(__DIR__)) . '/openapi.yaml';
            $body = is_file($file) ? (string) file_get_contents($file) : '';

            return Response::text($body, $body === '' ? 404 : 200, 'application/yaml; charset=utf-8');
        });
        $this->router->post('/mcp', fn (Request $request): Response => $this->mcpServer->handle($request));
    }

    private function rateLimit(Request $request): void
    {
        $limit = (int) $this->config->get('security.rate_limit', 120);
        if ($limit <= 0) {
            return;
        }

        $window = max(1, (int) $this->config->get('security.rate_window', 60));
        $identity = hash('sha256', $request->ip((bool) $this->config->get('security.trust_proxy', false)));
        $bucket = 'rl:' . intdiv(time(), $window) . ':' . $identity;
        $count = $this->cache->incr($bucket, $window * 2);
        $reset = (intdiv(time(), $window) + 1) * $window;
        $remaining = max(0, $limit - $count);

        $this->rateMeta = [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];

        if ($count > $limit) {
            throw new RateLimitException($limit, $remaining, $reset, max(1, $reset - time()));
        }
    }

    private function finalize(Response $response, Request $request, string $requestId, float $started): Response
    {
        $response = $this->cors->apply($response, $request);

        $elapsed = (int) ((microtime(true) - $started) * 1000);
        $response = $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '0',
            'Permissions-Policy' => 'interest-cohort=()',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src https: http: data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Request-Id' => $requestId,
            'X-Search-Time-Ms' => (string) $elapsed,
        ]);

        if ($this->rateMeta !== null) {
            $response = $response->withHeaders([
                'X-RateLimit-Limit' => (string) $this->rateMeta['limit'],
                'X-RateLimit-Remaining' => (string) $this->rateMeta['remaining'],
                'X-RateLimit-Reset' => (string) $this->rateMeta['reset'],
            ]);
        }

        if ($request->isSecure()) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->method === 'HEAD') {
            $response = $response->withBody('');
        }

        return $response;
    }
}
