<?php

declare(strict_types=1);

namespace SearPHP\Http;

final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;
    private bool $jsonParsed = false;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly string $body,
        private readonly array $server = [],
        private readonly string $basePath = '',
    ) {
    }

    public static function fromGlobals(?string $basePath = null): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $base = $basePath !== null ? self::normalizeBasePath($basePath) : self::detectBasePath($_SERVER);
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
            if (!is_string($path) || $path === '') {
                $path = '/';
            }
        }

        $headers = [];
        if (function_exists('getallheaders')) {
            $rawHeaders = getallheaders();
            if (is_array($rawHeaders)) {
                foreach ($rawHeaders as $name => $value) {
                    $headers[strtolower((string) $name)] = trim((string) $value);
                }
            }
        }

        if ($headers === []) {
            foreach ($_SERVER as $name => $value) {
                if (!is_string($name) || !str_starts_with($name, 'HTTP_')) {
                    continue;
                }
                $headerName = strtolower(str_replace('_', '-', substr($name, 5)));
                $headers[$headerName] = trim((string) $value);
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['content-type'] = trim((string) $_SERVER['CONTENT_TYPE']);
            }
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['content-length'] = trim((string) $_SERVER['CONTENT_LENGTH']);
            }
        }

        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = '';
        }

        return new self($method, $path, $_GET, $headers, $body, $_SERVER, $base);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Detect /subfolder deployments from the front-controller path.
     */
    private static function detectBasePath(array $server): string
    {
        $script = (string) ($server['SCRIPT_NAME'] ?? '');

        if (str_ends_with($script, '/public/index.php')) {
            return self::normalizeBasePath(substr($script, 0, -strlen('/public/index.php')));
        }

        if (str_ends_with($script, '/index.php')) {
            return self::normalizeBasePath(substr($script, 0, -strlen('/index.php')));
        }

        return '';
    }

    private static function normalizeBasePath(string $basePath): string
    {
        $basePath = trim($basePath);
        if ($basePath === '' || $basePath === '/') {
            return '';
        }

        if (!str_starts_with($basePath, '/')) {
            $basePath = '/' . $basePath;
        }

        return rtrim($basePath, '/');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Query string first, then JSON/form/text body.
     */
    public function input(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->query)) {
            $value = $this->query[$key];
            return is_scalar($value) ? (string) $value : $default;
        }

        $body = $this->bodyData();
        if (array_key_exists($key, $body)) {
            $value = $body[$key];
            return is_scalar($value) ? (string) $value : $default;
        }

        // A raw text/plain body is treated as the query.
        if (($key === 'q' || $key === 'query') && $this->isPlainText() && trim($this->body) !== '') {
            return trim($this->body);
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyData(): array
    {
        $contentType = strtolower($this->header('content-type', '') ?? '');

        if (str_contains($contentType, 'application/json')) {
            if (!$this->jsonParsed) {
                $this->jsonParsed = true;
                $decoded = json_decode($this->body, true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            }

            return $this->jsonBody ?? [];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $data = [];
            parse_str($this->body, $data);
            return $data;
        }

        return [];
    }

    public function isPlainText(): bool
    {
        return str_contains(strtolower($this->header('content-type', '') ?? ''), 'text/plain');
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('content-type', '') ?? ''), 'application/json');
    }

    public function accepts(string $mime): bool
    {
        $accept = strtolower($this->header('accept', '*/*') ?? '*/*');

        return str_contains($accept, strtolower($mime)) || str_contains($accept, '*/*');
    }

    public function preferredFormat(string $default = 'json'): string
    {
        $explicit = $this->input('format');
        if ($explicit !== null && $explicit !== '') {
            return strtolower($explicit);
        }

        $accept = strtolower($this->header('accept', '') ?? '');

        if (str_contains($accept, 'text/markdown')) {
            return 'md';
        }
        if (str_contains($accept, 'text/html')) {
            return 'html';
        }
        if (str_contains($accept, 'application/json')) {
            return 'json';
        }

        return $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->input($key);
        if ($value === null || !preg_match('/^-?\d+$/', $value)) {
            return $default;
        }

        return (int) $value;
    }

    public function ip(bool $trustProxy = false): string
    {
        if ($trustProxy) {
            $forwarded = $this->header('x-forwarded-for');
            if ($forwarded !== null && $forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return strtolower((string) $this->header('x-forwarded-proto', '')) === 'https';
    }

    public function origin(): ?string
    {
        $origin = $this->header('origin');
        if ($origin === null || $origin === '') {
            return null;
        }

        return rtrim($origin, '/');
    }

    public function requestId(): string
    {
        $provided = $this->header('x-request-id');
        if ($provided !== null && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $provided)) {
            return $provided;
        }

        return bin2hex(random_bytes(8));
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
