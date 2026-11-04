<?php

declare(strict_types=1);

namespace SearPHP\Http;

final class RateLimitException extends \RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $reset,
        public readonly int $retryAfter,
    ) {
        parent::__construct('Rate limit exceeded');
    }
}
