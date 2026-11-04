# API

Base URL: `http://localhost:8080`

The API requires an API key by default and is also rate-limited by IP. A PHP file cache stores results and rate-limit counters. There is no database.

## Authentication

Send one of:

```http
Authorization: Bearer sk_...
X-API-Key: sk_...
GET /search?q=rust&key=sk_...
```

Keys live in `config/api_keys.txt` (outside `public_html`). Use `sha256:<hash>` lines; plain lines are accepted but not recommended. Generate:

```bash
php bin/key.php --write "agent-name"
```

`GET /`, `/help`, `/health`, `/app.js`, `/robots.txt`, `/openapi.yaml`, and the empty browser forms `/search` and `/images` are public. Search/image/MCP/engines requests require the key.

The HTML UI has an **API key** panel; the key is stored only in the browser's `localStorage` and sent with its searches. Prefer the `Authorization` header for API clients, because query-string keys can appear in access logs.

All responses are UTF-8. JSON is encoded with unescaped slashes/Unicode and invalid UTF-8 substituted, so one bad result cannot break the whole response.

## CORS

CORS defaults to `Access-Control-Allow-Origin: *`, with `GET, POST, OPTIONS` and the headers `Content-Type, Accept, Authorization, X-API-Key, X-Requested-With, MCP-Protocol-Version, Mcp-Session-Id`.

`OPTIONS` requests return `204` with the full preflight response before rate limiting.

## Search

```http
GET /search?q=rust+programming&format=md&limit=10
GET /api/v1/search?q=rust+programming&format=md&limit=10
```

`/api/search`, `/api/v1/search`, `/api/images`, and `/api/v1/images` are aliases with the same behavior.

```http
POST /search
Content-Type: application/json

{
  "query": "rust programming",
  "format": "json",
  "limit": 10,
  "engines": "duckduckgo,bing,brave",
  "lang": "en",
  "safesearch": 0,
  "time": "month",
  "include_images": false
}
```

`POST /search` also accepts `application/x-www-form-urlencoded` and `text/plain` (the body is the query).

### Parameters

| Name | Type | Default | Notes |
|---|---|---|---|
| `q` / `query` | string | required | 1–400 characters |
| `format` | enum | negotiated | `md`, `json`, `searx`, `html` |
| `engines` | string | defaults | Comma-separated ids or aliases |
| `limit` | int | 20 | 1–50; engines may return fewer |
| `page` | int | 1 | 1–10 |
| `lang` | string | en | `en`, `de`, `pt-br`, etc. |
| `safesearch` | int | 0 | 0 off, 1 moderate, 2 strict |
| `time` | enum | — | `day`, `week`, `month`, `year` |
| `include_images` | bool | false | Adds image results |
| `download` | bool | false | Markdown attachment when `format=md` |
| `pretty` | bool | false | Pretty JSON |

### Content negotiation

| `Accept` | Result |
|---|---|
| `text/markdown` | `text/markdown; charset=utf-8` |
| `application/json` | `application/json; charset=utf-8` |
| `text/html` | HTML page |
| `*/*` | JSON |

An explicit `format` query parameter wins over `Accept`.

### Markdown

```bash
curl "http://localhost:8080/search?q=rust+programming&format=md"
```

```md
# rust programming

> 10 results · 863 ms · duckduckgo, bing, brave

## Results

1. [Rust Programming Language](<https://rust-lang.org/>)
   Rust is a fast, reliable, and productive programming language...
   `duckduckgo` · `bing` · `brave`
```

### JSON

```json
{
  "query": "rust programming",
  "ok": true,
  "count": 10,
  "image_count": 0,
  "took_ms": 863,
  "cached": false,
  "partial": false,
  "results": [
    {
      "title": "Rust Programming Language",
      "url": "https://rust-lang.org/",
      "snippet": "Rust is a fast, reliable, and productive programming language...",
      "engine": "duckduckgo",
      "engines": ["duckduckgo", "bing", "brave"],
      "rank": 1,
      "score": 0.087253,
      "published_at": null,
      "thumbnail": null,
      "position": 1
    }
  ],
  "images": [],
  "answers": [],
  "suggestions": [],
  "meta": {
    "page": 1,
    "limit": 20,
    "engines": {
      "duckduckgo": {"status": "ok", "ms": 657, "count": 10},
      "bing": {"status": "ok", "ms": 210, "count": 10},
      "brave": {"status": "ok", "ms": 855, "count": 20}
    },
    "pagination": {
      "page": 1,
      "limit": 20,
      "has_more": true,
      "max_page": 10,
      "next_page": 2
    }
  }
}
```

`ok` is true when there is content or when no engine failed. `partial` is true if any requested engine errored, was challenged, timed out, or hit a rate limit. Both can be true at once: useful results plus a failed engine.

### Pagination

- `limit` is the page size, maximum 50.
- `page` is 1–10.
- `meta.pagination.has_more` is true when at least one paginating engine filled its page.
- `next_page` is the next page number or `null`.
- There is no exact global total; `count`/`number_of_results` is the current page only.
- Wikipedia and DuckDuckGo Instant Answers do not paginate; their results are repeated on each page.

The HTML interface has Web/Images tabs and Previous/Next links that preserve the query, engines, language, safety, time range, and limit.

### SearXNG-compatible JSON

`format=searx` returns the shape used by SearXNG clients:

```json
{
  "query": "rust",
  "number_of_results": 10,
  "results": [
    {
      "url": "https://rust-lang.org/",
      "title": "Rust Programming Language",
      "content": "...",
      "engine": "duckduckgo",
      "engines": ["duckduckgo", "bing"],
      "score": 1.0,
      "category": "general",
      "publishedDate": null,
      "thumbnail": null,
      "img_src": null,
      "priority": ""
    }
  ],
  "answers": [],
  "corrections": [],
  "infoboxes": [],
  "suggestions": [],
  "unresponsive_engines": []
}
```

## Images

```http
GET /images?q=red+fox&format=json&limit=20
GET /images?q=red+fox&format=md
GET /api/v1/images?q=red+fox&format=json&limit=20
```

The HTML page has an **Images** tab; opening `/images` without a query shows the image search form.

Image JSON entries:

```json
{
  "title": "Red Fox",
  "image_url": "https://example.com/fox.jpg",
  "thumbnail_url": "https://example.com/fox-thumb.jpg",
  "page_url": "https://example.com/fox-page",
  "width": 1200,
  "height": 800,
  "engine": "duckduckgo_images",
  "engines": ["duckduckgo_images", "bing_images"],
  "rank": 1,
  "score": 0.030141,
  "position": 1
}
```

`/search?q=...&include_images=1` returns web results and images in the same response.

## Engines

```bash
curl http://localhost:8080/engines
```

Returns metadata for every pure-PHP adapter, recent stats, and defaults.

Ids:

```
duckduckgo, duckduckgo_lite, bing, brave, wikipedia, ddg_instant,
duckduckgo_images, bing_images, brave_images
```

Aliases: `ddg` → `duckduckgo`, `wiki` → `wikipedia`, `ddg-images` → `duckduckgo_images`.

## Health

```bash
curl http://localhost:8080/health
```

```json
{
  "status": "ok",
  "name": "SearPHP",
  "version": "1.0.0",
  "php": "8.5.12",
  "cache": {"driver": "file", "ok": true},
  "engines": {"enabled": 8, "web": 5, "images": 2},
  "time": "2026-11-04T16:33:45+00:00"
}
```

`status` becomes `degraded` if the file cache directory is not writable. Health is not rate-limited so orchestrators can probe it.

## MCP

`POST /mcp` implements JSON-RPC 2.0:

```bash
curl -s http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Tools:

- `web_search` — arguments: `q`, `limit`, `engines`, `lang`, `safesearch`, `time`
- `image_search` — arguments: `q`, `limit`, `engines`, `lang`, `safesearch`

The text content is Markdown. `structuredContent` contains the native JSON for clients that want it.

## Errors

```json
{
  "error": {
    "code": "bad_request",
    "message": "The query must contain between 1 and 400 characters."
  }
}
```

| Status | Code | Cause |
|---|---|---|
| 400 | `bad_request` | Invalid query, engine, format, language, time range |
| 401 | `unauthorized` | Missing/invalid API key |
| 404 | `not_found` | Unknown route or disabled MCP |
| 405 | `method_not_allowed` | Wrong HTTP method |
| 429 | `rate_limited` | Local rate limit; see `Retry-After` |
| 500 | `internal_error` | Unexpected failure |

Engine failures do not produce a 500. They appear in `meta.engines` and set `partial: true`.
