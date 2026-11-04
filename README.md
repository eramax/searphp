# SearPHP

A fast, self-hosted web search API built with **PHP 8.5 only**.

No database, no Google API keys, no SerpAPI, no Brave API key, no OpenSERP, no Go/Node/Chromium sidecar, and no external services. The app is one PHP codebase; the cache and rate-limit counters are plain files under `var/cache`.

It aggregates DuckDuckGo, Bing, Brave, Wikipedia, DuckDuckGo Images, and Bing Images, deduplicates and ranks the results, and returns **Markdown, JSON, or SearXNG-compatible JSON** for AI agents and command-line use.

```
GET /search?q=rust+programming&format=md
GET /search?q=rust+programming&format=json&limit=20
GET /images?q=red+fox&format=json
POST /mcp
```

## Engines

All of these are implemented in PHP and need no key.

| Engine | Web | Images | Notes |
|---|---|---|---|
| **DuckDuckGo** | ✅ | ✅ | Primary; POST HTML + Lite fallback + image `vqd` flow |
| **DuckDuckGo Lite** | ✅ | — | fallback when the HTML endpoint challenges |
| **Bing** | ✅ | ✅ | Web + async image endpoint |
| **Brave Search** | ✅ | ⚠️ | HTML; 429s under bursts, cached and rate-limited |
| **Wikipedia** | ✅ | — | MediaWiki API |
| **DuckDuckGo Instant Answers** | ✅ | — | abstracts/answers when available |

**Google is not included.** With PHP only, Google returns `403` or a JavaScript-only page and cannot be scraped reliably. Adding Google would require an external browser service or a keyed API, which you explicitly rejected.

**duck.ai is not included.** Its chat endpoint requires solving a JavaScript proof-of-work (`x-vqd-hash-1`), which needs a headless browser.

No other applications, external search services, API keys, or accounts are used.

## Quick start with Podman

```bash
cd searphp

podman build -t localhost/searphp:latest .

podman run -d --name searphp -p 8080:80 \
  -v searphp-cache:/var/www/html/var/cache \
  localhost/searphp:latest
```

Open <http://localhost:8080/> or:

```bash
curl "http://localhost:8080/search?q=rust+programming&format=md&limit=10"
```

With a Compose provider:

```bash
cp .env.example .env
podman compose up -d --build
```

The `searphp-cache` volume keeps cached searches across container restarts. If you delete it, the cache is rebuilt automatically.

## API

### Search

```http
GET  /search?q=rust+programming
POST /search
Content-Type: application/json

{"query":"rust programming","format":"md","limit":10}
```

`q` can come from the query string, a form body, JSON (`q` or `query`), or a raw `text/plain` body.

| Parameter | Default | Description |
|---|---|---|
| `q` | required | Query text, 1–400 characters |
| `format` | negotiated | `md`, `json`, `searx`, `html` |
| `engines` | defaults | Comma-separated engine ids; aliases: `ddg`, `wiki` |
| `limit` | `20` | 1–50; a maximum, not a guarantee |
| `page` | `1` | 1–10, passed to engines where supported |
| `lang` | `en` | ISO language, e.g. `en`, `de`, `pt-br` |
| `safesearch` | `0` | `0` off, `1` moderate, `2` strict |
| `time` | — | `day`, `week`, `month`, `year` |
| `include_images` | `false` | Add image results to a web search |
| `download` | `false` | `format=md&download=1` returns an attachment |
| `pretty` | `false` | Pretty-print JSON |

Content negotiation also works:

```bash
curl -H "Accept: text/markdown" "http://localhost:8080/search?q=php+8.5"
curl -H "Accept: application/json" "http://localhost:8080/search?q=php+8.5"
```

### Images

```http
GET /images?q=red+fox&format=json&limit=20
GET /images?q=red+fox&format=md
```

### Endpoints

| Endpoint | Purpose |
|---|---|
| `GET /engines` | Engine metadata, defaults, and recent latency/status |
| `GET /health` | PHP version, cache driver health, engine counts |
| `POST /mcp` | MCP JSON-RPC: `initialize`, `tools/list`, `tools/call` |
| `GET /openapi.yaml` | OpenAPI 3.1 document |
| `GET /robots.txt` | Disallow crawlers |

### Response headers

`X-Search-Time-Ms`, `X-Cache` (`HIT`/`MISS`), `X-Engines`, `X-Partial`, `X-Request-Id`, and `X-RateLimit-*` are exposed through CORS.

When one engine fails, the others still return. Inspect `meta.engines` in JSON or `X-Partial: 1` to see what happened.

## Configuration

All settings are environment variables; see `.env.example`.

| Variable | Default | Description |
|---|---|---|
| `CORS_ORIGINS` | `*` | `*` or a comma-separated allowlist |
| `RATE_LIMIT` | `120` | Requests per window per IP |
| `RATE_WINDOW` | `60` | Window in seconds |
| `CACHE_DRIVER` | `file` | `file` (or `none`, not recommended) |
| `CACHE_TTL` | `600` | Web result cache TTL |
| `CACHE_IMAGE_TTL` | `1800` | Image result cache TTL |
| `ENGINE_*` | see example | Enable/disable individual engines |
| `MCP_ENABLED` | `true` | Expose `POST /mcp` |
| `LOG_QUERIES` | `false` | Log full query text (hashes otherwise) |

There is no API-key authentication and no database. The API is open and limited by IP; put it behind a reverse proxy or firewall if it is exposed publicly.

## How it is fast

- No framework, no Composer runtime dependencies.
- Engines are queried in parallel with `curl_multi`; follow-up requests (DDG images, Lite fallback) reuse the same deadline.
- Total budget defaults to 2.6 s. Engines that miss it are reported as timed out and the response is still returned.
- File cache stores search results, engine stats, and rate-limit counters under `var/cache`.
- OPcache + JIT are enabled in the container.
- Results are cached by query, engines, language, safety, time range, images, and page.
- Measured locally: ~0.6–1.2 s uncached, ~2 ms on a file-cache hit.

## Security

- CORS defaults to `*` for the read-only API (no cookies, no credentials). Set an allowlist when needed.
- Per-IP rate limiting with `429` and `Retry-After`.
- Strict input validation; no user-supplied URL is fetched, so there is no SSRF path in the search API.
- Upstream TLS verification, HTTP/HTTPS only, redirect cap, response-size cap, and timeouts.
- Markdown escaping and HTML escaping; only `http`/`https` links are emitted.
- Security headers and a strict CSP on the small HTML page.
- No API keys, no external accounts, no database, and query text is not logged by default.

See [docs/SECURITY.md](docs/SECURITY.md).

## Tests

```bash
podman run --rm -v "$PWD:/app:Z" -w /app \
  docker.io/library/php:8.5-cli php tests/run.php
```

Live engine smoke test:

```bash
podman run --rm -v "$PWD:/app:Z" -w /app \
  docker.io/library/php:8.5-cli php bin/smoke.php "php 8.5 features"
```

CLI search:

```bash
podman run --rm -v "$PWD:/app:Z" -w /app \
  docker.io/library/php:8.5-cli php bin/search.php "rust programming" md
```

## Project layout

```
public/index.php        front controller
src/App.php             routing, rate limit, CORS, security headers
src/Engines/            pure-PHP engine adapters
src/Search/             query, ranker, dedupe, orchestration, cache keys
src/Format/             Markdown, JSON, SearXNG JSON, HTML
src/Cache/              PHP file cache
src/Mcp/                MCP JSON-RPC server
tests/                  offline fixtures and zero-dependency test runner
```

## Notes

- The file cache is local to one container/host. If you run multiple replicas, each has its own cache and rate counters.
- Expired files are ignored and overwritten; remove `var/cache` contents if you want to clear the cache manually.
- Search engines may rate-limit, challenge, or block automated requests. Use conservative limits and cache aggressively.
- Run it for personal or internal use, and respect each engine's terms.
