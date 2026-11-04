<?php

declare(strict_types=1);

use SearPHP\Engines\EngineRegistry;
use SearPHP\Http\HttpClient;
use SearPHP\Search\Query;

/** @var \SearPHP\Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$queryText = $argv[1] ?? 'php 8.5 features';
$query = new Query($queryText, limit: 10);

$http = new HttpClient($config);
$registry = new EngineRegistry($config);

$requests = [];
foreach ($registry->defaultWebIds() as $id) {
    $engine = $registry->get($id);
    if ($engine === null) {
        continue;
    }
    $request = $engine->request($query);
    if ($request !== null) {
        $requests[$id] = $request->withTimeout(min($request->timeoutMs, 12_000));
    }
}

echo "Web engines: " . implode(', ', array_keys($requests)) . PHP_EOL;
$responses = $http->fetchAll($requests);

foreach ($requests as $id => $request) {
    $engine = $registry->get($id);
    $response = $responses[$id] ?? null;
    if ($engine === null || $response === null) {
        echo "[$id] no response" . PHP_EOL;
        continue;
    }

    try {
        $outcome = $engine->parse($response, $query);
        printf(
            "[%-14s] http=%3d ms=%5d status=%-10s results=%2d images=%2d error=%s\n",
            $id,
            $response->status,
            (int) $response->ms,
            $outcome->status,
            count($outcome->results),
            count($outcome->images),
            $outcome->error ?? '-'
        );
        foreach (array_slice($outcome->results, 0, 2) as $result) {
            echo '  - ' . mb_substr($result->title, 0, 80) . ' | ' . $result->url . PHP_EOL;
        }
        foreach (array_slice($outcome->images, 0, 2) as $image) {
            echo '  - [image] ' . mb_substr($image->title, 0, 60) . ' | ' . $image->imageUrl . PHP_EOL;
        }
    } catch (Throwable $exception) {
        echo "[$id] parse error: " . $exception->getMessage() . PHP_EOL;
    }
}

$imageRequests = [];
foreach ($registry->defaultImageIds() as $id) {
    $engine = $registry->get($id);
    if ($engine === null) {
        continue;
    }
    $request = $engine->request($query);
    if ($request !== null) {
        $imageRequests[$id] = $request->withTimeout(min($request->timeoutMs, 12_000));
    }
}

if ($imageRequests !== []) {
    echo PHP_EOL . "Image engines: " . implode(', ', array_keys($imageRequests)) . PHP_EOL;
    $responses = $http->fetchAll($imageRequests);

    foreach ($imageRequests as $id => $request) {
        $engine = $registry->get($id);
        $response = $responses[$id] ?? null;
        if ($engine === null || $response === null) {
            continue;
        }

        try {
            $outcome = $engine->parse($response, $query);
            if ($outcome->followUp !== null) {
                $follow = $http->fetch($outcome->followUp);
                $outcome = $engine->parse($follow, $query);
                $response = $follow;
            }

            printf(
                "[%-18s] http=%3d ms=%5d status=%-10s images=%2d error=%s\n",
                $id,
                $response->status,
                (int) $response->ms,
                $outcome->status,
                count($outcome->images),
                $outcome->error ?? '-'
            );
            foreach (array_slice($outcome->images, 0, 2) as $image) {
                echo '  - ' . mb_substr($image->title, 0, 60) . ' | ' . $image->imageUrl . PHP_EOL;
            }
        } catch (Throwable $exception) {
            echo "[$id] parse error: " . $exception->getMessage() . PHP_EOL;
        }
    }
}
