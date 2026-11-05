# SearPHP

A fast, self-hosted web search API built with **PHP only** (8.2+, tested through 8.5).

No database, no Google API keys, no SerpAPI, no Brave API key, no OpenSERP, no Go/Node/Chromium sidecar, and no external search services. The app is one PHP codebase; the cache and rate-limit counters are plain files under `var/cache`.

SearPHP requires its own API key by default. Keys are stored hashed in `config/api_keys.txt` (outside `public_html`).

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

No other applications, external search services, external keys, or accounts are used. The only key is SearPHP's own API key.

## Quick start with Podman

```bash
cd searphp

podman build -t localhost/searphp:latest .

podman run -d --name searphp -p 8080:80 \
  -v searphp-cache:/var/www/html/var/cache \
  localhost/searphp:latest
```

Generate an API key before searching:

```bash
podman exec searphp php bin/key.php --write
podman exec searphp chmod 644 /var/www/html/config/api_keys.txt
```

The second command makes the **hashed** key file readable by Apache (`www-data`). The command prints the key once; keep it. Open <http://localhost:8080/>, click **API key**, paste it, and save it in the browser.

For a persistent key across container recreation, generate it on the host and bind-mount it read-only at `/var/www/html/config/api_keys.txt`, or use a Compose file mount for that single file.

For API clients:

```bash
KEY='sk_...'
curl -H "Authorization: Bearer $KEY" \
  "http://localhost:8080/search?q=rust+programming&format=md&limit=10"
```

With a Compose provider:

```bash
cp .env.example .env
podman compose up -d --build
podman compose exec app php bin/key.php --write
podman compose exec app chmod 644 /var/www/html/config/api_keys.txt
```

For Compose, bind-mount `config/api_keys.txt` into `/var/www/html/config/api_keys.txt` if you want the key to survive container recreation.

### Shared hosting (cPanel)

The project includes `.htaccess`, `.user.ini`, and a `shared-hosting/` front controller for normal PHP hosting. The safest layout puts the application outside `public_html` and only the wrapper inside it. See [docs/SHARED_HOSTING.md](docs/SHARED_HOSTING.md) for the step-by-step guide, 403 checks, permissions, and hardening.

PHP **8.2+** works (tested on 8.2, 8.3, and 8.5). No MySQL, SSH, Composer, or external service is required.

The `searphp-cache` volume keeps cached searches across container restarts. If you delete it, the cache is rebuilt automatically.

## API

### Authentication

All search/image/MCP/engines endpoints require one of:

```http
Authorization: Bearer sk_...
X-API-Key: sk_...
GET /search?q=rust&key=sk_...
```

The browser UI stores the key in `localStorage` and mirrors it into a same-site cookie, so refreshing a results page keeps working without the key in the URL. For API clients, prefer the `Authorization` header: query-string keys can appear in access logs.

Key file:

```
config/api_keys.txt
```

Use `sha256:<hash>` lines. Generate a key and append its hash:

```bash
php bin/key.php --write "agent-name"
```

or print a key and hash without writing:

```bash
php bin/key.php
```

`API_AUTH_ENABLED=false` disables the check (not recommended on a public host). `API_KEYS_FILE` can point to a file outside the project.

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
| `GET /help` | Public quick API guide |
| `GET /robots.txt` | Disallow crawlers |

Explicit API aliases:

```text
/api/search        /api/v1/search
/api/images        /api/v1/images
```

They accept the same parameters and return the same Markdown/JSON/SearXNG formats as `/search` and `/images`.

The HTML page has **Web** and **Images** tabs, plus Previous/Next pagination.

### Pagination

`limit` is the page size (maximum 50), and `page` is 1–10. The API returns:

```json
"meta": {
  "pagination": {
    "page": 1,
    "limit": 20,
    "has_more": true,
    "max_page": 10,
    "next_page": 2
  }
}
```

There is no exact global `total`, because each engine returns one page and results are de-duplicated across engines. `has_more` means at least one paginating engine filled its page. Use `page=2` for the next page.

### Response headers

`X-Search-Time-Ms`, `X-Cache` (`HIT`/`MISS`), `X-Engines`, `X-Partial`, `X-Request-Id`, and `X-RateLimit-*` are exposed through CORS.

When one engine fails, the others still return. Inspect `meta.engines` in JSON or `X-Partial: 1` to see what happened.

## Configuration

All settings are environment variables; see `.env.example`.

| Variable | Default | Description |
|---|---|---|
| `APP_BASE_PATH` | empty | Empty for a root domain; `/subfolder` if needed |
| `CORS_ORIGINS` | `*` | `*` or a comma-separated allowlist |
| `RATE_LIMIT` | `120` | Requests per window per IP |
| `RATE_WINDOW` | `60` | Window in seconds |
| `API_AUTH_ENABLED` | `true` | Require a key |
| `API_KEYS_FILE` | `config/api_keys.txt` | Key file path |
| `CACHE_DRIVER` | `file` | `file` (or `none`, not recommended) |
| `CACHE_TTL` | `600` | Web result cache TTL |
| `CACHE_IMAGE_TTL` | `1800` | Image result cache TTL |
| `ENGINE_*` | see example | Enable/disable individual engines |
| `MCP_ENABLED` | `true` | Expose `POST /mcp` |
| `LOG_QUERIES` | `false` | Log full query text (hashes otherwise) |

There is no database. API authentication is required by default and rate limiting is still applied per IP; put the service behind a reverse proxy or firewall as an extra layer.

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
- API key authentication is required by default; prefer `Authorization: Bearer` over query keys.
- Per-IP rate limiting with `429` and `Retry-After`.
- Strict input validation; no user-supplied URL is fetched, so there is no SSRF path in the search API.
- Upstream TLS verification, HTTP/HTTPS only, redirect cap, response-size cap, and timeouts.
- Markdown escaping and HTML escaping; only `http`/`https` links are emitted.
- Security headers and a strict CSP on the small HTML page.
- API keys are stored as SHA-256 hashes, not plaintext, and the key file is outside `public_html`.
- No external search accounts or database; query text is not logged by default.

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
