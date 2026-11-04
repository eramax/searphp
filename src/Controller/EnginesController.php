<?php

declare(strict_types=1);

namespace SearPHP\Controller;

use SearPHP\Engines\EngineRegistry;
use SearPHP\Http\Request;
use SearPHP\Http\Response;
use SearPHP\Search\EngineStats;

final class EnginesController
{
    public function __construct(
        private readonly EngineRegistry $engines,
        private readonly EngineStats $stats,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::json([
            'engines' => $this->engines->metadata(),
            'stats' => $this->stats->snapshot($this->engines->ids()),
            'defaults' => [
                'web' => $this->engines->defaultWebIds(),
                'images' => $this->engines->defaultImageIds(),
            ],
        ]);
    }
}
