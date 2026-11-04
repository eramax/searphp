# Engines

Every adapter is pure PHP and needs no API key. The app uses a PHP file cache for rate limits and results; there is no database.

Each adapter implements:

```php
interface EngineInterface
{
    public function id(): string;
    public function label(): string;
    public function categories(): array;
    public function weight(): float;
    public function request(Query $query): ?HttpRequest;
    public function parse(HttpResponse $response, Query $query): EngineOutcome;
}
```

`request()` returns one HTTP request. `parse()` returns results, images, answers, suggestions, a status, and optionally a **follow-up request**. The orchestrator runs all first requests in parallel with `curl_multi`, then runs follow-ups in a second parallel round.

## Included engines

### DuckDuckGo (`duckduckgo`)

- POST `https://html.duckduckgo.com/html/` with `q`, `kl`, optional `df` and `s`.
- A desktop GET is challenged. POST is the reliable path.
- If the response is `202` or contains the `jsa` challenge, `parse()` returns a Lite POST as a follow-up.
- User agent defaults to Safari 17.4; the Lite fallback uses Lynx.
- Ads (`result--ad`) are skipped.
- Links wrapped in `duckduckgo.com/l/?uddg=...` are decoded.

### DuckDuckGo Lite (`duckduckgo_lite`)

- POST `https://lite.duckduckgo.com/lite/`.
- Useful as an explicit fallback and for low-bandwidth environments.
- Not in the default engine list.

### Bing (`bing`)

- GET `https://www.bing.com/search?...&adlt=off|moderate|strict`.
- Parses `li.b_algo`, skips `li.b_ad`.
- Resolves `bing.com/ck/a?...&u=a1<base64url>` links.

### Brave (`brave`)

- GET `https://search.brave.com/search?...&source=web`.
- Parses `div.snippet[data-type="web"]`.
- Brave returns `429` after bursts. The adapter reports `rate_limited`; the circuit breaker and file cache reduce pressure.

### Wikipedia (`wikipedia`)

- MediaWiki API: `action=query&list=search`.
- Language subdomain follows `lang` (`de.wikipedia.org`, etc.).
- Good for factual/knowledge queries; weight 0.7.

### DuckDuckGo Instant Answers (`ddg_instant`)

- `api.duckduckgo.com/?format=json&no_html=1`.
- Produces abstract/answer/definition entries and related-topic results.
- Often empty for generic queries; that is normal.

## Images

### DuckDuckGo Images (`duckduckgo_images`)

Two steps:

1. GET the image landing page and extract `vqd`.
2. GET `https://duckduckgo.com/i.js?...&vqd=...`.

A typical response contains around 100 raw images; the ranker trims to `limit`.

### Bing Images (`bing_images`)

- GET `https://www.bing.com/images/async?...&mmasync=1`.
- Parses `a.iusc[m]` JSON (`murl`, `turl`, `purl`, `t`, `w`, `h`).

### Brave Images (`brave_images`)

Experimental and disabled by default. Brave rate-limits image pages aggressively; the parser is best-effort.

## Not included

### Google

Google's HTML endpoint returns `403` or a JavaScript-only page with zero results. With PHP only, there is no reliable way to scrape it. Adding Google would require a separate browser service or a keyed API, both of which are intentionally excluded.

### duck.ai

The public chat endpoint requires solving an obfuscated JavaScript proof-of-work (`x-vqd-hash-1`). That needs a headless browser. It is not included.

## Rate limits and circuit breakers

- `engines.<id>.rate_per_min` is enforced locally per engine with a counter stored in the file cache.
- Three consecutive failures open a 60-second circuit for that engine.
- A failed engine never fails the request; it is listed in `meta.engines` and sets `partial: true`.
- Partial responses are cached for only 90 seconds so a recovered engine is picked up quickly.

## Adding an engine

1. Create `src/Engines/MyEngine.php` extending `AbstractEngine`.
2. Implement `id()`, `label()`, `categories()`, `request()`, `parse()`.
3. Add a config block under `engines.my_engine` with `enabled`, `weight`, `timeout_ms`, `rate_per_min`.
4. Register the class in `EngineRegistry`.
5. Add fixtures and a test in `tests/run.php`.
6. If it needs a follow-up (token, pagination), return `EngineOutcome(followUp: HttpRequest...)`.

Keep `request()` and `parse()` pure and testable; no network calls inside them.
