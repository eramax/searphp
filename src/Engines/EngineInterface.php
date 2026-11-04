<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;

interface EngineInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * @return list<string>
     */
    public function categories(): array;

    public function weight(): float;

    public function paginationSupported(): bool;

    public function pageSize(): int;

    public function request(Query $query): ?HttpRequest;

    public function parse(HttpResponse $response, Query $query): EngineOutcome;
}
