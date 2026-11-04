<?php
/**
 * @var string $appName
 * @var string $version
 * @var string $appUrl
 * @var string $basePath
 * @var bool $authRequired
 */

use SearPHP\Format\Html;

$base = rtrim($basePath ?? '', '/');
$homeUrl = $base !== '' ? $base . '/' : '/';
$searchUrl = $appUrl . $base . '/api/v1/search';
$imagesUrl = $appUrl . $base . '/api/v1/images';
$uiUrl = $appUrl . $homeUrl;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Help — <?= Html::e($appName) ?></title>
    <style>
        :root { color-scheme: light dark; --bg:#fbfbfa; --fg:#171717; --muted:#666; --line:#e4e4e2; --card:#fff; --accent:#0f766e; --code:#f2f2f0; }
        @media (prefers-color-scheme: dark) { :root { --bg:#101211; --fg:#ececeb; --muted:#9b9b98; --line:#2a2d2b; --card:#171a19; --accent:#5eead4; --code:#151817; } }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.6 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .wrap { max-width:860px; margin:0 auto; padding:28px 18px 64px; }
        header { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin-bottom:20px; }
        .brand { color:var(--fg); text-decoration:none; font-weight:750; font-size:20px; letter-spacing:-.03em; }
        .brand span { color:var(--accent); }
        .muted { color:var(--muted); font-size:13px; }
        h1 { font-size:28px; letter-spacing:-.03em; margin:10px 0 6px; }
        h2 { font-size:17px; margin:30px 0 10px; }
        p { color:var(--muted); }
        code, pre { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:13px; }
        code { background:var(--code); padding:2px 5px; border-radius:5px; color:var(--fg); }
        pre { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; overflow:auto; color:var(--fg); line-height:1.55; }
        pre code { background:none; padding:0; }
        table { width:100%; border-collapse:collapse; font-size:14px; margin:10px 0 16px; }
        th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { color:var(--muted); font-weight:600; }
        .note { background:var(--card); border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; padding:12px 14px; color:var(--muted); font-size:14px; }
        a { color:var(--accent); }
        footer { border-top:1px solid var(--line); margin-top:36px; padding-top:14px; color:var(--muted); font-size:12px; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="<?= Html::e($homeUrl) ?>"><?= Html::e($appName) ?><span>.</span></a>
        <span class="muted">v<?= Html::e($version) ?></span>
    </header>

    <h1>Quick API guide</h1>
    <p>Search the web or images and get Markdown or JSON. Use the key panel on the <a href="<?= Html::e($homeUrl) ?>">search page</a> for the browser UI.</p>

    <div class="note">
        <strong>API key required.</strong>
        Send <code>Authorization: Bearer sk_...</code>, <code>X-API-Key: sk_...</code>, or <code>?key=sk_...</code>.
        Prefer the Authorization header: query-string keys can appear in web-server access logs.
    </div>

    <h2>Search — Markdown</h2>
    <pre><code>KEY='sk_your_key_here'

curl -H "Authorization: Bearer $KEY" \
  "<?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=md&amp;limit=20"</code></pre>

    <h2>Search — JSON</h2>
    <pre><code>curl -H "X-API-Key: $KEY" \
  "<?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=json&amp;limit=20"</code></pre>

    <h2>POST search</h2>
    <pre><code>curl -X POST \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"query":"rust programming","format":"md","limit":10}' \
  "<?= Html::e($searchUrl) ?>"</code></pre>

    <p>You can also POST <code>application/x-www-form-urlencoded</code> or a raw <code>text/plain</code> body containing the query.</p>

    <h2>Images</h2>
    <pre><code>curl -H "Authorization: Bearer $KEY" \
  "<?= Html::e($imagesUrl) ?>?q=red+fox&amp;format=json&amp;limit=20"</code></pre>

    <h2>Aliases and formats</h2>
    <table>
        <tr><th>Endpoint</th><th>Purpose</th></tr>
        <tr><td><code>/api/v1/search</code></td><td>Web search (also <code>/api/search</code>)</td></tr>
        <tr><td><code>/api/v1/images</code></td><td>Image search (also <code>/api/images</code>)</td></tr>
        <tr><td><code>format=md</code></td><td>Markdown</td></tr>
        <tr><td><code>format=json</code></td><td>Native JSON</td></tr>
        <tr><td><code>format=searx</code></td><td>SearXNG-compatible JSON</td></tr>
    </table>
    <p><code>Accept: text/markdown</code>, <code>Accept: application/json</code>, and <code>Accept: text/html</code> also select the output.</p>

    <h2>Pagination</h2>
    <pre><code>?q=rust&amp;limit=20&amp;page=2</code></pre>
    <p><code>limit</code> is 1–50 and <code>page</code> is 1–10. JSON includes:</p>
    <pre><code>"meta": {
  "pagination": {
    "page": 1,
    "limit": 20,
    "has_more": true,
    "max_page": 10,
    "next_page": 2
  }
}</code></pre>
    <p>There is no exact global total; <code>has_more</code> means at least one paginating engine filled its page.</p>

    <h2>Other endpoints</h2>
    <table>
        <tr><th>Endpoint</th><th>Auth</th><th>Purpose</th></tr>
        <tr><td><code>/health</code></td><td>public</td><td>Health and cache status</td></tr>
        <tr><td><code>/openapi.yaml</code></td><td>public</td><td>OpenAPI 3.1 document</td></tr>
        <tr><td><code>/engines</code></td><td>key</td><td>Engine metadata and recent stats</td></tr>
        <tr><td><code>POST /mcp</code></td><td>key</td><td>MCP JSON-RPC: <code>web_search</code>, <code>image_search</code></td></tr>
    </table>

    <h2>MCP</h2>
    <pre><code>curl -X POST \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  "<?= Html::e($appUrl . $base) ?>/mcp"</code></pre>

    <h2>Browser UI</h2>
    <p>Open <a href="<?= Html::e($uiUrl) ?>"><?= Html::e($uiUrl) ?></a>, click <strong>API key (required)</strong>, paste the key, and save it. It is stored in this browser's <code>localStorage</code> and sent with the UI's searches and internal links.</p>

    <footer>
        <?= Html::e($appName) ?> — PHP-only search. <?php if ($authRequired): ?>API key authentication is enabled.<?php else: ?>API key authentication is disabled.<?php endif; ?>
    </footer>
</div>
</body>
</html>
