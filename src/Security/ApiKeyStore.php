<?php

declare(strict_types=1);

namespace SearPHP\Security;

use SearPHP\Config;
use SearPHP\Http\Request;

final class ApiKeyStore
{
    /** @var list<string>|null */
    private ?array $keys = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('security.api_auth', true);
    }

    /**
     * Accepts a key from X-API-Key, Authorization: Bearer, or ?key=.
     */
    public function providedKey(Request $request): ?string
    {
        $headerKey = trim((string) $request->header('x-api-key', ''));
        if ($headerKey !== '') {
            return $headerKey;
        }

        $authorization = trim((string) $request->header('authorization', ''));
        if ($authorization !== '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        // The browser UI stores the key in localStorage and mirrors it into a
        // same-site cookie so page refreshes do not need the key in the URL.
        $cookieKey = $request->cookie('searphp_key');
        if (is_string($cookieKey) && $cookieKey !== '') {
            return $cookieKey;
        }

        $queryKey = trim((string) ($request->input('key') ?? $request->input('api_key') ?? ''));

        return $queryKey !== '' ? $queryKey : null;
    }

    public function validateRequest(Request $request): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        $provided = $this->providedKey($request);
        if ($provided === null) {
            return false;
        }

        return $this->validate($provided);
    }

    public function validate(string $provided): bool
    {
        $keys = $this->keys();
        if ($keys === []) {
            return false;
        }

        $providedHash = hash('sha256', $provided);

        foreach ($keys as $stored) {
            if (str_starts_with($stored, 'sha256:')) {
                if (hash_equals(substr($stored, 7), $providedHash)) {
                    return true;
                }
                continue;
            }

            if (hash_equals($stored, $provided)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $file = (string) $this->config->get('security.api_keys_file', '');
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return $this->keys = [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $this->keys = [];
        }

        $keys = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'sha256:')) {
                $hash = strtolower(substr($line, 7));
                if (preg_match('/^[a-f0-9]{64}$/', $hash) === 1) {
                    $keys[] = 'sha256:' . $hash;
                }
                continue;
            }

            $keys[] = $line;
        }

        return $this->keys = $keys;
    }
}
