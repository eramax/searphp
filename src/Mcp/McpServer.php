<?php

declare(strict_types=1);

namespace SearPHP\Mcp;

use InvalidArgumentException;
use SearPHP\Config;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Format\JsonFormatter;
use SearPHP\Format\MarkdownFormatter;
use SearPHP\Http\Request;
use SearPHP\Http\Response;
use SearPHP\Search\Query;
use SearPHP\Search\SearchService;

/**
 * Minimal stateless MCP server over HTTP JSON-RPC.
 *
 * Tools: web_search, image_search. The text content is Markdown, which keeps
 * responses compact for language models.
 */
final class McpServer
{
    public function __construct(
        private readonly Config $config,
        private readonly SearchService $search,
        private readonly EngineRegistry $engines,
        private readonly MarkdownFormatter $markdown,
        private readonly JsonFormatter $json,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->config->get('mcp.enabled', true)) {
            return Response::error('not_found', 'MCP endpoint is disabled', 404);
        }

        if ($request->method !== 'POST') {
            return Response::error('method_not_allowed', 'MCP requires POST', 405)
                ->withHeader('Allow', 'POST');
        }

        $payload = $request->bodyData();
        if ($payload === [] && trim($request->body) !== '') {
            $decoded = json_decode($request->body, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if ($payload === []) {
            return $this->rpcError(null, -32700, 'Parse error');
        }

        $id = $payload['id'] ?? null;
        $method = isset($payload['method']) && is_string($payload['method']) ? $payload['method'] : '';
        if ($method === '') {
            return $this->rpcError($id, -32600, 'Invalid Request');
        }

        if ($id === null && str_starts_with($method, 'notifications/')) {
            return Response::noContent(202);
        }

        return match ($method) {
            'initialize' => $this->initialize($id, is_array($payload['params'] ?? null) ? $payload['params'] : []),
            'ping' => $this->result($id, new \stdClass()),
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($id, is_array($payload['params'] ?? null) ? $payload['params'] : []),
            default => $this->rpcError($id, -32601, 'Method not found: ' . $method),
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function initialize(mixed $id, array $params): Response
    {
        $requested = isset($params['protocolVersion']) && is_string($params['protocolVersion'])
            ? $params['protocolVersion']
            : '2024-11-05';
        $protocolVersion = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) === 1
            ? $requested
            : '2024-11-05';

        return $this->result($id, [
            'protocolVersion' => $protocolVersion,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => (string) $this->config->get('app.name', 'SearPHP'),
                'version' => (string) $this->config->get('app.version', '1.0.0'),
            ],
        ])->withHeader('Mcp-Session-Id', bin2hex(random_bytes(16)));
    }

    private function toolsList(mixed $id): Response
    {
        $engineDescription = 'Comma-separated engine ids: ' . implode(', ', $this->engines->ids());

        return $this->result($id, [
            'tools' => [
                [
                    'name' => 'web_search',
                    'description' => 'Search the web and return ranked results as compact Markdown. ' . $engineDescription,
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => ['type' => 'string', 'description' => 'Search query (1-400 characters)'],
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                            'engines' => ['type' => 'string', 'description' => 'Optional comma-separated engine ids'],
                            'lang' => ['type' => 'string', 'default' => 'en'],
                            'safesearch' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'default' => 0],
                            'time' => ['type' => 'string', 'enum' => ['day', 'week', 'month', 'year']],
                        ],
                        'required' => ['q'],
                    ],
                ],
                [
                    'name' => 'image_search',
                    'description' => 'Search for images and return image URLs with source pages.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => ['type' => 'string', 'description' => 'Image search query'],
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                            'engines' => ['type' => 'string', 'description' => 'Optional comma-separated image engine ids'],
                            'lang' => ['type' => 'string', 'default' => 'en'],
                            'safesearch' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'default' => 0],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function toolsCall(mixed $id, array $params): Response
    {
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

        if (!in_array($name, ['web_search', 'image_search'], true)) {
            return $this->rpcError($id, -32602, 'Unknown tool: ' . $name);
        }

        try {
            $query = $this->queryFromArguments($args);
            $set = $name === 'image_search'
                ? $this->search->images($query)
                : $this->search->search($query);

            $text = $this->markdown->format($set, $query);
            $structured = $this->json->format($set, $query);

            return $this->result($id, [
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
                'structuredContent' => $structured,
                'isError' => false,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->result($id, [
                'content' => [
                    ['type' => 'text', 'text' => 'Error: ' . $exception->getMessage()],
                ],
                'isError' => true,
            ]);
        } catch (\Throwable $exception) {
            return $this->result($id, [
                'content' => [
                    ['type' => 'text', 'text' => 'Tool execution failed.'],
                ],
                'isError' => true,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function queryFromArguments(array $args): Query
    {
        $params = [];
        foreach (['q', 'limit', 'engines', 'lang', 'safesearch', 'time'] as $key) {
            if (array_key_exists($key, $args) && is_scalar($args[$key])) {
                $params[$key] = (string) $args[$key];
            }
        }

        $request = new Request('GET', '/search', $params, [], '', []);

        return Query::fromRequest($request, $this->config, $this->engines->ids());
    }

    private function result(mixed $id, mixed $result): Response
    {
        return Response::json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function rpcError(mixed $id, int $code, string $message): Response
    {
        return Response::json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $code === -32700 || $code === -32600 ? 400 : 200);
    }
}
