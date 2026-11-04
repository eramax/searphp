<?php

declare(strict_types=1);

namespace SearPHP\Controller;

use RuntimeException;
use SearPHP\Config;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Format\HtmlFormatter;
use SearPHP\Format\JsonFormatter;
use SearPHP\Format\MarkdownFormatter;
use SearPHP\Format\SearxFormatter;
use SearPHP\Http\Request;
use SearPHP\Http\Response;
use SearPHP\Search\Query;
use SearPHP\Search\SearchResultSet;
use SearPHP\Search\SearchService;

final class SearchController
{
    public function __construct(
        private readonly Config $config,
        private readonly SearchService $search,
        private readonly EngineRegistry $engines,
        private readonly MarkdownFormatter $markdown,
        private readonly JsonFormatter $json,
        private readonly SearxFormatter $searx,
        private readonly HtmlFormatter $html,
    ) {
    }

    public function home(Request $request): Response
    {
        return Response::html($this->html->page(null, null, $request->basePath()));
    }

    public function search(Request $request): Response
    {
        $query = Query::fromRequest($request, $this->config, $this->engines->ids());
        $set = $this->search->search($query);

        return $this->respond($set, $query, $request->basePath());
    }

    public function images(Request $request): Response
    {
        $query = Query::fromRequest($request, $this->config, $this->engines->ids());
        $query = $query->withIncludeImages(true);

        if (!in_array($query->format, ['json', 'md', 'html', 'searx'], true)) {
            $query = $query->withFormat('json');
        }

        $set = $this->search->images($query);

        return $this->respond($set, $query, $request->basePath());
    }

    private function respond(SearchResultSet $set, Query $query, string $basePath = ''): Response
    {
        $response = match ($query->format) {
            'md' => Response::markdown($this->markdown->format($set, $query)),
            'html' => Response::html($this->html->page($set, $query, $basePath)),
            'searx' => Response::json($this->searx->format($set, $query), 200, $query->pretty),
            default => Response::json($this->json->format($set, $query), 200, $query->pretty),
        };

        $engines = [];
        foreach ($set->engineStatus as $engine => $status) {
            if ($status['status'] !== 'disabled') {
                $engines[] = $engine;
            }
        }

        $response = $response->withHeaders([
            'X-Search-Time-Ms' => (string) $set->tookMs,
            'X-Cache' => $set->cached ? 'HIT' : 'MISS',
            'X-Engines' => implode(',', $engines),
            'X-Partial' => $set->partial ? '1' : '0',
            'Cache-Control' => 'no-store',
        ]);

        if ($query->format === 'md' && $query->download) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $query->q) ?? 'search';
            $slug = trim(strtolower($slug), '-');
            if ($slug === '') {
                $slug = 'search';
            }
            $response = $response->withHeader(
                'Content-Disposition',
                'attachment; filename="search-' . substr($slug, 0, 60) . '.md"'
            );
        }

        return $response;
    }
}
