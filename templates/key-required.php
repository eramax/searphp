<?php
/**
 * @var string $appName
 * @var string $version
 * @var string $basePath
 * @var string $assetVersion
 */

use SearPHP\Format\Html;

$base = rtrim($basePath ?? '', '/');
$homeUrl = $base !== '' ? $base . '/' : '/';
$helpUrl = $base . '/help';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>API key required — <?= Html::e($appName) ?></title>
    <style>
        :root { color-scheme: light dark; --bg:#fbfbfa; --fg:#171717; --muted:#666; --line:#e4e4e2; --card:#fff; --accent:#0f766e; --accent-soft:#e6f4f1; --warn:#92400e; --warn-bg:#fef3c7; }
        @media (prefers-color-scheme: dark) { :root { --bg:#101211; --fg:#ececeb; --muted:#9b9b98; --line:#2a2d2b; --card:#171a19; --accent:#5eead4; --accent-soft:#17302c; --warn:#fcd34d; --warn-bg:#33270d; } }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.55 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .wrap { max-width:680px; margin:0 auto; padding:34px 18px; }
        header { display:flex; justify-content:space-between; align-items:baseline; gap:12px; margin-bottom:24px; }
        .brand { color:var(--fg); text-decoration:none; font-weight:750; font-size:20px; letter-spacing:-.03em; }
        .brand span { color:var(--accent); }
        .muted { color:var(--muted); font-size:13px; }
        h1 { font-size:24px; letter-spacing:-.02em; margin:0 0 6px; }
        p { color:var(--muted); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; margin-top:18px; }
        .key-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:10px; }
        .key-row input { flex:1 1 240px; border:1px solid var(--line); border-radius:8px; padding:10px; background:var(--bg); color:var(--fg); font:inherit; }
        .key-row button { border:1px solid var(--line); border-radius:8px; padding:9px 12px; background:var(--bg); color:var(--fg); font:inherit; cursor:pointer; }
        .key-row button.primary { border-color:var(--accent); background:var(--accent); color:#fff; font-weight:650; }
        .key-status { font-size:12px; color:var(--muted); }
        .key-status.ok { color:var(--accent); }
        .key-status.warn { color:var(--warn); }
        .note { background:var(--warn-bg); color:var(--warn); border:1px solid color-mix(in srgb,var(--warn) 30%,transparent); border-radius:10px; padding:10px 12px; font-size:14px; margin-top:14px; }
        code { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.92em; }
        a { color:var(--accent); }
        footer { border-top:1px solid var(--line); margin-top:26px; padding-top:14px; color:var(--muted); font-size:12px; }
    </style>
</head>
<body data-key-page="1">
<div class="wrap">
    <header>
        <a class="brand" href="<?= Html::e($homeUrl) ?>"><?= Html::e($appName) ?><span>.</span></a>
        <span class="muted">v<?= Html::e($version) ?></span>
    </header>

    <h1>API key required</h1>
    <p>This SearPHP instance requires a key. Enter it once; it will be stored in this browser and used for later searches.</p>

    <div class="card">
        <label for="api-key-input" class="muted">API key</label>
        <div class="key-row">
            <input id="api-key-input" type="password" placeholder="Paste API key" autocomplete="off" spellcheck="false" autofocus>
            <button type="button" id="api-key-save" class="primary">Save and continue</button>
            <button type="button" id="api-key-clear">Clear</button>
            <span id="api-key-status" class="key-status">No key saved.</span>
        </div>
        <div class="note">
            If you have a key saved in this browser, the page will retry automatically.
            Otherwise paste the key and press <strong>Save and continue</strong>.
        </div>
    </div>

    <p class="muted" style="margin-top:18px">
        Need the API? Read the <a href="<?= Html::e($helpUrl) ?>">quick guide</a>.
        For API clients use <code>Authorization: Bearer sk_...</code>.
    </p>

    <footer><?= Html::e($appName) ?> — key-based access. No key is stored in the URL.</footer>
</div>
<script src="<?= Html::e(($base !== '' ? $base : '') . '/app.js?v=' . rawurlencode($assetVersion)) ?>" defer></script>
</body>
</html>
