<?php

declare(strict_types=1);

use SearPHP\Cache\FileCache;
use SearPHP\Config;
use SearPHP\Engines\BingImages;
use SearPHP\Engines\BingWeb;
use SearPHP\Engines\BraveWeb;
use SearPHP\Engines\DuckDuckGoImages;
use SearPHP\Engines\DuckDuckGoLite;
use SearPHP\Engines\DuckDuckGoWeb;
use SearPHP\Engines\EngineRegistry;
use SearPHP\Engines\Wikipedia;
use SearPHP\Format\JsonFormatter;
use SearPHP\Format\MarkdownFormatter;
use SearPHP\Format\SearxFormatter;
use SearPHP\Http\Cors;
use SearPHP\Http\HttpResponse;
use SearPHP\Http\Request;
use SearPHP\Http\Response;
use SearPHP\Search\EngineOutcome as SearchEngineOutcome;
use SearPHP\Search\ImageResult;
use SearPHP\Search\Query;
use SearPHP\Search\Ranker;
use SearPHP\Search\Result;
use SearPHP\Search\SearchResultSet;
use SearPHP\Search\UrlCanonicalizer;
use SearPHP\Security\ApiKeyStore;

/** @var Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';
$fixtures = __DIR__ . '/fixtures';

$passed = 0;
$failed = 0;

function test(string $name, callable $callback): void
{
    global $passed, $failed;

    try {
        $callback();
        $passed++;
        echo "  ok  {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL  {$name}\n      " . $exception->getMessage() . "\n";
    }
}

function assert_true(bool $condition, string $message = 'Expected true'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message !== ''
                ? $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'
                : 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message !== '' ? $message : 'Missing: ' . $needle);
    }
}

function fixture(string $name): string
{
    global $fixtures;
    $content = file_get_contents($fixtures . '/' . $name);
    if ($content === false) {
        throw new RuntimeException('Missing fixture: ' . $name);
    }

    return $content;
}

function response(string $body, string $url = 'https://example.com/', int $status = 200): HttpResponse
{
    return new HttpResponse($status, [], $body, $url, 10.0);
}

echo "SearPHP tests\n\n";

test('URL canonicalization strips tracking, fragments and default ports', function (): void {
    assert_same(
        'https://example.com/a?b=2',
        UrlCanonicalizer::normalize('https://Example.com:443/a?utm_source=x&b=2#fragment')
    );
    assert_same(
        'example.com/a?b=2',
        UrlCanonicalizer::key('https://www.example.com/a/?b=2')
    );
});

test('DuckDuckGo redirect and Bing redirect are resolved', function (): void {
    assert_same(
        'https://example.com/one',
        UrlCanonicalizer::fromRedirect('https://duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.com%2Fone%3Futm_source%3Dtest')
    );

    $encoded = rtrim(strtr(base64_encode('https://example.com/bing'), '+/', '-_'), '=');
    assert_same(
        'https://example.com/bing',
        UrlCanonicalizer::fromRedirect('https://www.bing.com/ck/a?u=a1' . $encoded)
    );
});

test('DuckDuckGo HTML parser skips ads and decodes links', function () use ($config): void {
    $engine = new DuckDuckGoWeb($config);
    $outcome = $engine->parse(response(fixture('ddg.html')), new Query('test'));

    assert_same('ok', $outcome->status);
    assert_same(2, count($outcome->results));
    assert_same('Example One', $outcome->results[0]->title);
    assert_same('https://example.com/one?b=2', $outcome->results[0]->url);
    assert_contains('First snippet', $outcome->results[0]->snippet);
});

test('DuckDuckGo challenge produces a Lite fallback request', function () use ($config): void {
    $engine = new DuckDuckGoWeb($config);
    $outcome = $engine->parse(response(fixture('ddg_challenge.html'), 'https://html.duckduckgo.com/html/', 202), new Query('test'));

    assert_same('challenged', $outcome->status);
    assert_true($outcome->followUp !== null);
    assert_contains('lite.duckduckgo.com', $outcome->followUp?->url ?? '');
});

test('DuckDuckGo Lite parser', function () use ($config): void {
    $engine = new DuckDuckGoLite($config);
    $outcome = $engine->parse(response(fixture('ddg_lite.html')), new Query('test'));

    assert_same(2, count($outcome->results));
    assert_same('Lite One', $outcome->results[0]->title);
    assert_same('Lite snippet one.', $outcome->results[0]->snippet);
});

test('Bing parser skips ads and resolves wrapped links', function () use ($config): void {
    $engine = new BingWeb($config);
    $outcome = $engine->parse(response(fixture('bing.html')), new Query('test'));

    assert_same('ok', $outcome->status);
    assert_same(2, count($outcome->results));
    assert_same('https://example.com/bing', $outcome->results[0]->url);
    assert_same('2026-01-02', $outcome->results[1]->publishedAt);
});

test('Brave parser ignores captcha strings in scripts when results exist', function () use ($config): void {
    $engine = new BraveWeb($config);
    $outcome = $engine->parse(response(fixture('brave.html')), new Query('test'));

    assert_same('ok', $outcome->status);
    assert_same(2, count($outcome->results));
    assert_same('Brave One', $outcome->results[0]->title);
});

test('Wikipedia parser', function () use ($config): void {
    $engine = new Wikipedia($config);
    $outcome = $engine->parse(response(fixture('wikipedia.json'), 'https://en.wikipedia.org/w/api.php'), new Query('rust'));

    assert_same(2, count($outcome->results));
    assert_same('Rust (programming language)', $outcome->results[0]->title);
    assert_true(str_contains($outcome->results[0]->url, 'wikipedia.org/wiki/'));
});

test('DuckDuckGo Images performs the vqd follow-up and parses JSON', function () use ($config): void {
    $engine = new DuckDuckGoImages($config);
    $first = $engine->parse(response(fixture('ddg_images_vqd.html'), 'https://duckduckgo.com/?q=fox'), new Query('fox'));

    assert_true($first->followUp !== null);
    assert_contains('vqd=4-123456789', $first->followUp?->url ?? '');

    $second = $engine->parse(
        response(fixture('ddg_images.json'), 'https://duckduckgo.com/i.js?q=fox'),
        new Query('fox')
    );

    assert_same(1, count($second->images));
    assert_same('https://example.com/fox.jpg', $second->images[0]->imageUrl);
    assert_same(1200, $second->images[0]->width);
});

test('Bing Images parser', function () use ($config): void {
    $engine = new BingImages($config);
    $outcome = $engine->parse(response(fixture('bing_images.html')), new Query('fox'));

    assert_same(2, count($outcome->images));
    assert_same('https://example.com/bing-image.jpg', $outcome->images[0]->imageUrl);
});

test('Markdown output escapes text and uses safe autolinks', function (): void {
    $result = new Result('Hello [world]', 'https://example.com/a_(b)', 'Snippet *here*', 'duckduckgo');
    $set = new SearchResultSet(
        query: 'test',
        results: [$result],
        engineStatus: ['duckduckgo' => ['status' => 'ok', 'ms' => 10, 'count' => 1]],
        tookMs: 12,
        limit: 10,
    );

    $markdown = (new MarkdownFormatter())->format($set, new Query('test'));

    assert_contains('\[world\]', $markdown);
    assert_contains('(<https://example.com/a_(b)>)', $markdown);
    assert_contains('\*here\*', $markdown);

    $answerSet = new SearchResultSet(
        query: 'test',
        answers: [['text' => 'Read [this](javascript:alert(1)) <script>alert(2)</script>']],
        engineStatus: ['duckduckgo' => ['status' => 'ok', 'ms' => 1, 'count' => 1]],
    );
    $rendered = (new MarkdownFormatter())->format($answerSet, new Query('test'));
    assert_true(!str_contains($rendered, 'javascript:'));
    assert_contains('&lt;script&gt;', $rendered);
});

test('JSON formatter schema', function (): void {
    $result = new Result('Title', 'https://example.com', 'Snippet', 'bing');
    $set = new SearchResultSet(
        query: 'test',
        results: [$result],
        engineStatus: ['bing' => ['status' => 'ok', 'ms' => 10, 'count' => 1]],
        tookMs: 12,
        page: 1,
        limit: 3,
        hasMore: true,
        maxPage: 10,
    );

    $data = (new JsonFormatter())->format($set, new Query('test'));

    assert_same('test', $data['query']);
    assert_same(1, $data['count']);
    assert_same(1, $data['results'][0]['position']);
    assert_same('bing', $data['results'][0]['engine']);
    assert_same(true, $data['meta']['pagination']['has_more']);
    assert_same(2, $data['meta']['pagination']['next_page']);
});

test('SearXNG-compatible formatter', function (): void {
    $result = new Result('Title', 'https://example.com', 'Snippet', 'brave');
    $set = new SearchResultSet(
        query: 'test',
        results: [$result],
        engineStatus: ['brave' => ['status' => 'rate_limited', 'ms' => 10, 'count' => 0, 'error' => 'HTTP 429']],
        tookMs: 12,
    );

    $data = (new SearxFormatter())->format($set, new Query('test'));

    assert_same(1, $data['number_of_results']);
    assert_same('brave', $data['results'][0]['engine']);
    assert_same([['brave', 'HTTP 429']], $data['unresponsive_engines']);
});

test('Query validation and aliases', function () use ($config): void {
    $registry = new EngineRegistry($config);

    $valid = Query::fromRequest(
        new Request('GET', '/search', ['q' => 'rust', 'engines' => 'ddg', 'limit' => '999'], [], '', []),
        $config,
        $registry->ids()
    );
    assert_same(['duckduckgo'], $valid->engines);
    assert_same(50, $valid->limit);

    try {
        Query::fromRequest(new Request('GET', '/search', ['q' => ''], [], '', []), $config, $registry->ids());
        throw new RuntimeException('Empty query was accepted');
    } catch (InvalidArgumentException) {
    }

    try {
        Query::fromRequest(
            new Request('GET', '/search', ['q' => 'rust', 'engines' => 'nope'], [], '', []),
            $config,
            $registry->ids()
        );
        throw new RuntimeException('Unknown engine was accepted');
    } catch (InvalidArgumentException) {
    }
});

test('Ranker deduplicates URLs and merges engines', function (): void {
    $a = new Result('Rust', 'https://example.com/rust', 'First', 'duckduckgo', 1);
    $b = new Result('Rust language', 'https://www.example.com/rust/', 'Second longer', 'bing', 1);

    $outcomes = [
        'duckduckgo' => new SearchEngineOutcome(results: [$a], status: 'ok'),
        'bing' => new SearchEngineOutcome(results: [$b], status: 'ok'),
    ];

    $results = (new Ranker())->mergeResults(
        $outcomes,
        new Query('rust', limit: 10),
        ['duckduckgo' => 1.0, 'bing' => 0.9]
    );

    assert_same(1, count($results));
    assert_same(['duckduckgo', 'bing'], $results[0]->engines);
    assert_same('Second longer', $results[0]->snippet);
});

test('CORS wildcard and allowlist', function (): void {
    $cors = new Cors(new Config(['cors' => ['origins' => '*', 'max_age' => 600]]));
    $request = new Request('GET', '/search', [], ['origin' => 'https://agent.example'], '', []);
    $response = $cors->apply(Response::text('ok'), $request);

    assert_same('*', $response->headers()['Access-Control-Allow-Origin'] ?? null);

    $cors = new Cors(new Config(['cors' => ['origins' => 'https://agent.example', 'max_age' => 600]]));
    $response = $cors->apply(Response::text('ok'), $request);
    assert_same('https://agent.example', $response->headers()['Access-Control-Allow-Origin'] ?? null);
    assert_same('Origin', $response->headers()['Vary'] ?? null);

    $unknown = new Request('GET', '/search', [], ['origin' => 'https://evil.example'], '', []);
    $response = $cors->apply(Response::text('ok'), $unknown);
    assert_true(!isset($response->headers()['Access-Control-Allow-Origin']));
});

test('API key store accepts headers, bearer, and query keys', function (): void {
    $file = sys_get_temp_dir() . '/searphp-keys-' . bin2hex(random_bytes(4));
    $plain = 'sk_plain_test_key';
    $hashed = 'sk_hashed_test_key';
    file_put_contents($file, "sha256:" . hash('sha256', $hashed) . "\n" . $plain . "\n");

    $store = new ApiKeyStore(new Config([
        'security' => [
            'api_auth' => true,
            'api_keys_file' => $file,
        ],
    ]));

    assert_true($store->validate($plain));
    assert_true($store->validate($hashed));
    assert_true(!$store->validate('wrong'));

    $bearer = new Request('GET', '/search', [], ['authorization' => 'Bearer ' . $hashed], '', []);
    assert_same($hashed, $store->providedKey($bearer));
    assert_true($store->validateRequest($bearer));

    $header = new Request('GET', '/search', [], ['x-api-key' => $plain], '', []);
    assert_true($store->validateRequest($header));

    $query = new Request('GET', '/search', ['key' => $plain], [], '', []);
    assert_true($store->validateRequest($query));
    assert_true(!$store->validateRequest(new Request('GET', '/search', [], [], '', [])));

    @unlink($file);
});

test('File cache get/set/incr', function (): void {
    $dir = sys_get_temp_dir() . '/searphp-test-' . bin2hex(random_bytes(4));
    $cache = new FileCache($dir);

    $cache->set('a', ['x' => 1], 60);
    assert_same(['x' => 1], $cache->get('a'));
    assert_same(1, $cache->incr('counter', 60));
    assert_same(2, $cache->incr('counter', 60));
    $cache->delete('a');
    assert_same(null, $cache->get('a'));
});

test('Engine registry exposes the tested core engines', function () use ($config): void {
    $registry = new EngineRegistry($config);
    $web = $registry->defaultWebIds();

    assert_true(in_array('duckduckgo', $web, true));
    assert_true(in_array('bing', $web, true));
    assert_true(in_array('brave', $web, true));
    assert_true(!in_array('brave_images', $registry->defaultImageIds(), true));
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
