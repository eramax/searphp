<?php
/**
 * @var string $appName
 * @var string $version
 * @var \SearPHP\Search\SearchResultSet|null $set
 * @var \SearPHP\Search\Query|null $query
 * @var list<string> $engines
 * @var string $basePath
 * @var bool $imagesMode
 * @var bool $authRequired
 * @var string $assetVersion
 */

use SearPHP\Format\Html;

$q = $query?->q ?? '';
$format = $query?->format ?? 'html';
$base = rtrim($basePath ?? '', '/');
$homeUrl = $base !== '' ? $base . '/' : '/';
$helpUrl = $base . '/help';
$searchUrl = $base . '/search';
$imagesBase = $base . '/images';
$formAction = $imagesMode ? $imagesBase : $searchUrl;
$webModeUrl = $q !== '' ? $searchUrl . '?' . http_build_query(['q' => $q], '', '&', PHP_QUERY_RFC3986) : $homeUrl;
$imagesModeUrl = $imagesBase . ($q !== '' ? '?' . http_build_query(['q' => $q], '', '&', PHP_QUERY_RFC3986) : '');
$jsonUrl = $searchUrl . '?' . http_build_query(['q' => $q, 'format' => 'json'], '', '&', PHP_QUERY_RFC3986);
$mdUrl = $searchUrl . '?' . http_build_query(['q' => $q, 'format' => 'md'], '', '&', PHP_QUERY_RFC3986);
$imagesUrl = $imagesBase . '?' . http_build_query(['q' => $q, 'format' => 'html'], '', '&', PHP_QUERY_RFC3986);

$pageParams = ['q' => $q, 'format' => 'html', 'limit' => $query?->limit ?? 20];
if ($query !== null) {
    if ($query->engines !== []) {
        $pageParams['engines'] = implode(',', $query->engines);
    }
    if ($query->safesearch > 0) {
        $pageParams['safesearch'] = $query->safesearch;
    }
    if ($query->timeRange !== null) {
        $pageParams['time'] = $query->timeRange;
    }
    if ($query->includeImages) {
        $pageParams['include_images'] = '1';
    }
}
$paginationBase = $imagesMode ? $imagesBase : $searchUrl;
$pageUrl = static fn (int $page): string => $paginationBase . '?' . http_build_query(
    array_merge($pageParams, ['page' => $page]),
    '',
    '&',
    PHP_QUERY_RFC3986
);
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
        .help-link { color: var(--accent); text-decoration: none; font-size: 13px; }
        .header-right { display: flex; align-items: baseline; gap: 12px; }
        .tagline { color: var(--muted); margin: -8px 0 22px; font-size: 14px; }
        .modes { display: flex; gap: 6px; margin: 0 0 12px; }
        .modes a {
            color: var(--muted); text-decoration: none; font-size: 13px;
            border: 1px solid transparent; border-radius: 999px; padding: 5px 12px;
        }
        .modes a.active { color: var(--accent); background: var(--accent-soft); border-color: color-mix(in srgb, var(--accent) 25%, transparent); }
        .modes a:hover { color: var(--fg); }
        .key-panel { margin: 0 0 12px; font-size: 13px; color: var(--muted); }
        .key-panel summary { cursor: pointer; }
        .key-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 9px; }
        .key-row input {
            flex: 1 1 220px; border: 1px solid var(--line); border-radius: 8px;
            padding: 8px 10px; background: var(--bg); color: var(--fg); font: inherit;
        }
        .key-row button {
            border: 1px solid var(--line); border-radius: 8px; padding: 8px 11px;
            background: var(--card); color: var(--fg); font: inherit; cursor: pointer;
        }
        .key-status { font-size: 12px; }
        .key-status.ok { color: var(--accent); }
        .key-status.warn { color: var(--warn); }
        .key-status.muted { color: var(--muted); }
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
        .meta { color: var(--muted); font-size: 13px; margin: 14px 0 6px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .meta-note { color: var(--muted); font-size: 12px; margin: 0 0 18px; }
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
        .image-modal { border: 0; padding: 0; background: transparent; max-width: 94vw; max-height: 94vh; }
        .image-modal::backdrop { background: rgb(0 0 0 / 0.78); }
        .image-modal .modal-card { position: relative; background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 10px; }
        .image-modal img { display: block; max-width: 92vw; max-height: 78vh; width: auto; height: auto; border-radius: 8px; }
        .image-modal .modal-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 4px 2px; color: var(--muted); font-size: 12px; }
        .image-modal .modal-meta a { color: var(--accent); text-decoration: none; white-space: nowrap; }
        .image-modal .modal-close { position: absolute; top: 6px; right: 6px; border: 1px solid var(--line); background: var(--card); color: var(--fg); border-radius: 999px; width: 30px; height: 30px; font-size: 18px; line-height: 1; cursor: pointer; }
        .suggestions { display: flex; gap: 7px; flex-wrap: wrap; }
        .suggestions a { color: var(--accent); text-decoration: none; border: 1px solid var(--line); border-radius: 999px; padding: 4px 10px; font-size: 13px; background: var(--card); }
        details { margin-top: 22px; color: var(--muted); font-size: 13px; }
        details summary { cursor: pointer; }
        details ul { margin: 8px 0 0; padding-left: 20px; }
        .links { display: flex; gap: 14px; flex-wrap: wrap; margin: 18px 0 0; font-size: 13px; }
        .links a { color: var(--accent); text-decoration: none; }
        .pagination { display: flex; align-items: center; gap: 10px; margin: 24px 0 0; font-size: 13px; }
        .pagination a { color: var(--accent); text-decoration: none; border: 1px solid var(--line); padding: 6px 12px; border-radius: 8px; background: var(--card); }
        .pagination .current { color: var(--muted); }
        .pagination .disabled { color: var(--muted); opacity: 0.45; padding: 6px 0; }
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
        <div class="header-right">
            <a class="help-link" href="<?= Html::e($helpUrl) ?>">API help</a>
            <span class="version">v<?= Html::e($version) ?></span>
        </div>
    </header>

    <p class="tagline">Fast web and image search, Markdown and JSON for agents. No trackers.</p>

    <div class="modes" role="tablist" aria-label="Search type">
        <a role="tab" data-auth-link class="<?= $imagesMode ? '' : 'active' ?>" aria-selected="<?= $imagesMode ? 'false' : 'true' ?>" href="<?= Html::e($webModeUrl) ?>">Web</a>
        <a role="tab" data-auth-link class="<?= $imagesMode ? 'active' : '' ?>" aria-selected="<?= $imagesMode ? 'true' : 'false' ?>" href="<?= Html::e($imagesModeUrl) ?>">Images</a>
    </div>

    <details class="key-panel">
        <summary>API key <?= $authRequired ? '(required)' : '(optional)' ?></summary>
        <div class="key-row">
            <input id="api-key-input" type="password" placeholder="Paste API key" autocomplete="off" spellcheck="false">
            <button type="button" id="api-key-save">Save key</button>
            <button type="button" id="api-key-clear">Clear</button>
            <span id="api-key-status" class="key-status muted">No key saved.</span>
        </div>
        <p>Stored in this browser (localStorage plus a same-site cookie for refresh) and sent with this UI's searches. For API clients use <code>Authorization: Bearer</code>.</p>
    </details>

    <form class="search" action="<?= Html::e($formAction) ?>" method="get" data-auth-required="<?= $authRequired ? '1' : '0' ?>">
        <input type="hidden" name="key" id="api-key-field" value="">
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
        <button type="submit"><?= $imagesMode ? 'Search Images' : 'Search' ?></button>
    </form>

    <?php if ($set !== null): ?>
        <div class="meta">
            <span class="pill" title="Final results after de-duplication, ranking, and the limit parameter"><?= count($set->results) ?> shown</span>
            <?php if ($set->images !== []): ?><span class="pill"><?= count($set->images) ?> images</span><?php endif; ?>
            <span title="<?= $set->cached ? 'Served from the PHP file cache; no engine requests were made' : 'Time spent fetching and ranking results' ?>"><?= $set->cached ? 'cached' : Html::e((string) $set->tookMs) . ' ms' ?></span>
            <?php foreach ($engines as $engine): ?>
                <?php if (($set->engineStatus[$engine]['status'] ?? '') !== 'disabled'): ?>
                    <span class="pill" title="Raw results returned by <?= Html::e($engine) ?> before de-duplication"><?= Html::e($engine) ?> · <?= Html::e((string) ($set->engineStatus[$engine]['count'] ?? 0)) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="meta-note">
            “shown” is the final list after de-duplication and the <code>limit</code> cap.
            Each engine number is its raw result count before de-duplication; it is not the number displayed.
        </p>

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
                    <?php
                    $imageUrl = Html::url($image->imageUrl);
                    if ($imageUrl === '') {
                        continue;
                    }
                    $imageTitle = $image->title !== '' ? $image->title : $image->pageUrl;
                    $imageSource = Html::url($image->pageUrl);
                    ?>
                    <figure>
                        <a href="<?= $imageUrl ?>"
                           data-image-modal
                           data-full="<?= $imageUrl ?>"
                           data-title="<?= Html::e($imageTitle) ?>"
                           data-source="<?= $imageSource ?>"
                           target="_blank"
                           rel="noopener noreferrer"><img src="<?= $imageUrl ?>" alt="<?= Html::e($imageTitle) ?>" loading="lazy" referrerpolicy="no-referrer"></a>
                        <figcaption><?= Html::e($imageTitle) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($query !== null && ($set->page > 1 || $set->hasMore)): ?>
            <nav class="pagination" aria-label="Search results pages">
                <?php if ($set->page > 1): ?>
                    <a data-auth-link href="<?= Html::e($pageUrl($set->page - 1)) ?>" rel="prev">← Previous</a>
                <?php else: ?>
                    <span class="disabled">← Previous</span>
                <?php endif; ?>
                <span class="current">Page <?= Html::e((string) $set->page) ?></span>
                <?php if ($set->hasMore && $set->page < $set->maxPage): ?>
                    <a data-auth-link href="<?= Html::e($pageUrl($set->page + 1)) ?>" rel="next">Next →</a>
                <?php else: ?>
                    <span class="disabled">Next →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php if ($set->suggestions !== []): ?>
            <h2>Related searches</h2>
            <div class="suggestions">
                <?php foreach ($set->suggestions as $suggestion): ?>
                    <a data-auth-link href="<?= Html::e($searchUrl . '?' . http_build_query(['q' => $suggestion], '', '&', PHP_QUERY_RFC3986)) ?>"><?= Html::e($suggestion) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="links">
            <a data-auth-link href="<?= Html::e($jsonUrl) ?>">JSON</a>
            <a data-auth-link href="<?= Html::e($mdUrl) ?>">Markdown</a>
            <a data-auth-link href="<?= Html::e($imagesUrl) ?>">Images</a>
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
            <div><strong>API key required.</strong> Use the key panel above, or send <code>Authorization: Bearer sk_...</code>.</div>
            <div style="margin-top:8px"><code>curl -H "Authorization: Bearer $KEY" "<?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=md"</code></div>
            <div><code>curl -H "X-API-Key: $KEY" "<?= Html::e($searchUrl) ?>?q=rust+programming&amp;format=json&amp;limit=20"</code></div>
            <div><code>curl -H "Authorization: Bearer $KEY" "<?= Html::e($imagesBase) ?>?q=red+fox&amp;format=json"</code></div>
            <div style="margin-top:8px"><a href="<?= Html::e($helpUrl) ?>">Full quick guide: authentication, Markdown/JSON, pagination, images, MCP →</a></div>
        </div>
    <?php endif; ?>

    <footer>
        <?= Html::e($appName) ?> aggregates DuckDuckGo, Bing, Brave, Wikipedia and optional engines.
        Respect each engine's terms and rate limits.
        <a href="<?= Html::e($helpUrl) ?>">API help</a>
    </footer>
</div>

<dialog id="image-modal" class="image-modal" aria-label="Image preview">
    <div class="modal-card">
        <form method="dialog">
            <button class="modal-close" aria-label="Close image preview">×</button>
        </form>
        <img id="image-modal-img" alt="">
        <div class="modal-meta">
            <span id="image-modal-title"></span>
            <a id="image-modal-source" href="#" target="_blank" rel="noopener noreferrer" hidden>Open source page</a>
        </div>
    </div>
</dialog>

<script src="<?= Html::e(($base !== '' ? $base : '') . '/app.js?v=' . rawurlencode($assetVersion)) ?>" defer></script>
</body>
</html>
