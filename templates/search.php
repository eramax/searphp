<?php
/**
 * @var string $appName
 * @var string $version
 * @var \SearPHP\Search\SearchResultSet|null $set
 * @var \SearPHP\Search\Query|null $query
 * @var list<string> $engines
 * @var string $basePath
 */

use SearPHP\Format\Html;

$q = $query?->q ?? '';
$format = $query?->format ?? 'html';
$base = rtrim($basePath ?? '', '/');
$homeUrl = $base !== '' ? $base . '/' : '/';
$searchUrl = $base . '/search';
$imagesBase = $base . '/images';
$jsonUrl = $searchUrl . '?' . http_build_query(['q' => $q, 'format' => 'json'], '', '&', PHP_QUERY_RFC3986);
$mdUrl = $searchUrl . '?' . http_build_query(['q' => $q, 'format' => 'md'], '', '&', PHP_QUERY_RFC3986);
$imagesUrl = $imagesBase . '?' . http_build_query(['q' => $q, 'format' => 'html'], '', '&', PHP_QUERY_RFC3986);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= Html::e($set !== null ? $set->query . ' — ' . $appName : $appName) ?></title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #fbfbfa;
            --fg: #171717;
            --muted: #6b6b6b;
            --line: #e4e4e2;
            --card: #ffffff;
            --accent: #0f766e;
            --accent-soft: #e6f4f1;
            --warn: #92400e;
            --warn-bg: #fef3c7;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #101211;
                --fg: #ececeb;
                --muted: #9b9b98;
                --line: #2a2d2b;
                --card: #171a19;
                --accent: #5eead4;
                --accent-soft: #17302c;
                --warn: #fcd34d;
                --warn-bg: #33270d;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            font: 16px/1.55 ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 880px; margin: 0 auto; padding: 28px 18px 64px; }
        header { display: flex; align-items: baseline; gap: 12px; justify-content: space-between; margin-bottom: 22px; }
        .brand { font-weight: 750; letter-spacing: -0.03em; font-size: 20px; text-decoration: none; color: var(--fg); }
        .brand span { color: var(--accent); }
        .version { color: var(--muted); font-size: 12px; }
        .tagline { color: var(--muted); margin: -8px 0 22px; font-size: 14px; }
        form.search {
            display: flex; gap: 8px; flex-wrap: wrap;
            background: var(--card); border: 1px solid var(--line);
            padding: 10px; border-radius: 14px;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }
        form.search input[type="search"] {
            flex: 1 1 280px; min-width: 180px;
            border: 1px solid var(--line); border-radius: 9px;
            padding: 11px 12px; font: inherit; background: var(--bg); color: var(--fg);
        }
        form.search input[type="search"]:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        form.search select, form.search button {
            border: 1px solid var(--line); border-radius: 9px; padding: 10px 11px;
            font: inherit; background: var(--bg); color: var(--fg);
        }
        form.search button {
            border-color: var(--accent); background: var(--accent); color: #fff;
            font-weight: 650; cursor: pointer;
        }
        .meta { color: var(--muted); font-size: 13px; margin: 14px 0 18px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .pill { border: 1px solid var(--line); border-radius: 999px; padding: 2px 9px; background: var(--card); }
        .warn { color: var(--warn); background: var(--warn-bg); border: 1px solid color-mix(in srgb, var(--warn) 30%, transparent); padding: 10px 12px; border-radius: 10px; font-size: 14px; margin: 14px 0; }
        h2 { font-size: 16px; letter-spacing: -0.01em; margin: 28px 0 12px; }
        ol.results { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        ol.results li {
            background: var(--card); border: 1px solid var(--line); border-radius: 12px;
            padding: 14px 16px;
        }
        ol.results a.title { color: var(--fg); text-decoration: none; font-weight: 650; font-size: 17px; }
        ol.results a.title:hover { text-decoration: underline; text-decoration-color: var(--accent); }
        .url { color: var(--accent); font-size: 12px; overflow-wrap: anywhere; margin-top: 2px; }
        .snippet { color: var(--muted); margin-top: 8px; font-size: 14px; }
        .badges { margin-top: 9px; display: flex; gap: 6px; flex-wrap: wrap; }
        .badge { font-size: 11px; color: var(--muted); border: 1px solid var(--line); border-radius: 999px; padding: 1px 7px; }
        .answer { background: var(--accent-soft); border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent); border-radius: 12px; padding: 14px 16px; }
        .answer p { margin: 0; white-space: pre-wrap; }
        .images { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .images figure { margin: 0; background: var(--card); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .images img { display: block; width: 100%; height: 120px; object-fit: cover; background: var(--line); }
        .images figcaption { padding: 7px 9px; font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .suggestions { display: flex; gap: 7px; flex-wrap: wrap; }
        .suggestions a { color: var(--accent); text-decoration: none; border: 1px solid var(--line); border-radius: 999px; padding: 4px 10px; font-size: 13px; background: var(--card); }
        details { margin-top: 22px; color: var(--muted); font-size: 13px; }
        details summary { cursor: pointer; }
        details ul { margin: 8px 0 0; padding-left: 20px; }
        .links { display: flex; gap: 14px; flex-wrap: wrap; margin: 18px 0 0; font-size: 13px; }
        .links a { color: var(--accent); text-decoration: none; }
        footer { margin-top: 38px; color: var(--muted); font-size: 12px; border-top: 1px solid var(--line); padding-top: 14px; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.92em; }
        .examples { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; font-size: 13px; color: var(--muted); }
        .examples code { color: var(--fg); }
        @media (max-width: 600px) {
            .wrap { padding: 18px 12px 48px; }
            header { align-items: center; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="<?= Html::e($homeUrl) ?>"><?= Html::e($appName) ?><span>.</span></a>
        <span class="version">v<?= Html::e($version) ?></span>
    </header>

    <p class="tagline">Fast web search, Markdown and JSON for agents. No trackers.</p>

    <form class="search" action="<?= Html::e($searchUrl) ?>" method="get">
        <input type="search" name="q" value="<?= Html::e($q) ?>" placeholder="Search the web…" autofocus>
        <select name="format" aria-label="Response format">
            <option value="html"<?= $format === 'html' ? ' selected' : '' ?>>HTML</option>
            <option value="json"<?= $format === 'json' ? ' selected' : '' ?>>JSON</option>
            <option value="md"<?= $format === 'md' ? ' selected' : '' ?>>Markdown</option>
            <option value="searx"<?= $format === 'searx' ? ' selected' : '' ?>>SearXNG JSON</option>
        </select>
        <select name="safesearch" aria-label="Safe search">
            <option value="0"<?= ($query?->safesearch ?? 0) === 0 ? ' selected' : '' ?>>Safe off</option>
            <option value="1"<?= ($query?->safesearch ?? 0) === 1 ? ' selected' : '' ?>>Moderate</option>
            <option value="2"<?= ($query?->safesearch ?? 0) === 2 ? ' selected' : '' ?>>Strict</option>
        </select>
        <button type="submit">Search</button>
    </form>

    <?php if ($set !== null): ?>
        <div class="meta">
            <span class="pill"><?= count($set->results) ?> results</span>
            <?php if ($set->images !== []): ?><span class="pill"><?= count($set->images) ?> images</span><?php endif; ?>
            <span><?= $set->cached ? 'cached' : Html::e((string) $set->tookMs) . ' ms' ?></span>
            <?php foreach ($engines as $engine): ?>
                <?php if (($set->engineStatus[$engine]['status'] ?? '') !== 'disabled'): ?>
                    <span class="pill"><?= Html::e($engine) ?> · <?= Html::e((string) ($set->engineStatus[$engine]['count'] ?? 0)) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($set->partial): ?>
            <div class="warn">Some engines failed or were rate-limited. Results are partial.</div>
        <?php endif; ?>

        <?php if ($set->answers !== []): ?>
            <h2>Instant answers</h2>
            <?php foreach ($set->answers as $answer): ?>
                <div class="answer" style="margin-bottom:8px"><p><?= Html::e((string) ($answer['text'] ?? '')) ?></p></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($set->results !== []): ?>
            <h2>Results</h2>
            <ol class="results">
                <?php foreach ($set->results as $result): ?>
                    <?php $resultUrl = Html::url($result->url); if ($resultUrl === '') { continue; } ?>
                    <li>
                        <a class="title" href="<?= $resultUrl ?>" target="_blank" rel="noopener noreferrer"><?= Html::e($result->title !== '' ? $result->title : $result->url) ?></a>
                        <div class="url"><?= Html::e($result->url) ?></div>
                        <?php if ($result->snippet !== ''): ?>
                            <div class="snippet"><?= Html::e($result->snippet) ?></div>
                        <?php endif; ?>
                        <div class="badges">
                            <?php foreach ($result->engines as $engine): ?>
                                <span class="badge"><?= Html::e($engine) ?></span>
                            <?php endforeach; ?>
                            <?php if ($result->publishedAt !== null): ?><span class="badge"><?= Html::e($result->publishedAt) ?></span><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($set->images !== []): ?>
            <h2>Images</h2>
            <div class="images">
                <?php foreach ($set->images as $image): ?>
                    <?php $imageUrl = Html::url($image->imageUrl); if ($imageUrl === '') { continue; } ?>
                    <figure>
                        <a href="<?= $imageUrl ?>" target="_blank" rel="noopener noreferrer"><img src="<?= $imageUrl ?>" alt="<?= Html::e($image->title) ?>" loading="lazy" referrerpolicy="no-referrer"></a>
                        <figcaption><?= Html::e($image->title !== '' ? $image->title : $image->pageUrl) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($set->suggestions !== []): ?>
            <h2>Related searches</h2>
            <div class="suggestions">
                <?php foreach ($set->suggestions as $suggestion): ?>
                    <a href="<?= Html::e($searchUrl . '?' . http_build_query(['q' => $suggestion], '', '&', PHP_QUERY_RFC3986)) ?>"><?= Html::e($suggestion) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="links">
            <a href="<?= Html::e($jsonUrl) ?>">JSON</a>
            <a href="<?= Html::e($mdUrl) ?>">Markdown</a>
            <a href="<?= Html::e($imagesUrl) ?>">Images</a>
        </div>

        <?php if ($set->partial): ?>
            <details>
                <summary>Engine details</summary>
                <ul>
                    <?php foreach ($set->engineStatus as $engine => $status): ?>
                        <li><code><?= Html::e($engine) ?></code>: <?= Html::e($status['status']) ?> · <?= Html::e((string) $status['count']) ?> results · <?= Html::e((string) $status['ms']) ?> ms<?= isset($status['error']) ? ' — ' . Html::e($status['error']) : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    <?php else: ?>
        <div class="examples" style="margin-top:18px">
            <div><code>GET <?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=md</code></div>
            <div><code>GET <?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=json&amp;limit=20</code></div>
            <div><code>GET <?= Html::e($imagesBase) ?>?q=red+fox&amp;format=json</code></div>
            <div><code>POST /search</code> with a JSON or text/plain body</div>
        </div>
    <?php endif; ?>

    <footer>
        <?= Html::e($appName) ?> aggregates DuckDuckGo, Bing, Brave, Wikipedia and optional engines.
        Respect each engine's terms and rate limits.
    </footer>
</div>
</body>
</html>
