<?php

declare(strict_types=1);

namespace SearPHP\Http;

use SearPHP\Config;

final class HttpClient
{
    private \CurlShareHandle $share;

    public function __construct(private readonly Config $config)
    {
        $this->share = curl_share_init();
        curl_share_setopt($this->share, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);

        if (defined('CURL_LOCK_DATA_SSL_SESSION')) {
            curl_share_setopt($this->share, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
        }
        if (defined('CURL_LOCK_DATA_CONNECT')) {
            curl_share_setopt($this->share, CURLSHOPT_SHARE, constant('CURL_LOCK_DATA_CONNECT'));
        }
    }

    /**
     * @param array<string, HttpRequest> $requests
     * @return array<string, HttpResponse>
     */
    public function fetchAll(array $requests, ?float $deadline = null): array
    {
        if ($requests === []) {
            return [];
        }

        $budgetMs = (int) $this->config->get('http.budget_ms', 2600);
        $deadline ??= microtime(true) + ($budgetMs / 1000);
        $maxBytes = (int) $this->config->get('http.max_bytes', 2_097_152);

        $multi = curl_multi_init();
        /** @var array<int, array{key: string, handle: \CurlHandle, transfer: CurlTransfer, started: float}> $transfers */
        $transfers = [];

        foreach ($requests as $key => $request) {
            $remainingMs = max(100, (int) (($deadline - microtime(true)) * 1000));
            $timeoutMs = min($request->timeoutMs, $remainingMs);
            $transfer = new CurlTransfer($request, $maxBytes);
            $handle = $this->makeHandle($request, $transfer, $timeoutMs);
            $transfers[spl_object_id($handle)] = [
                'key' => $key,
                'handle' => $handle,
                'transfer' => $transfer,
                'started' => microtime(true),
            ];
            curl_multi_add_handle($multi, $handle);
        }

        $responses = [];
        $running = 0;

        do {
            do {
                $multiStatus = curl_multi_exec($multi, $running);
            } while ($multiStatus === CURLM_CALL_MULTI_PERFORM);

            while (($info = curl_multi_info_read($multi)) !== false) {
                $handle = $info['handle'];
                $id = spl_object_id($handle);
                if (!isset($transfers[$id])) {
                    continue;
                }

                $entry = $transfers[$id];
                $responses[$entry['key']] = $this->buildResponse(
                    $handle,
                    $entry['transfer'],
                    $entry['started']
                );

                curl_multi_remove_handle($multi, $handle);
                unset($transfers[$id]);
            }

            if ($running > 0) {
                if (curl_multi_select($multi, 0.05) === -1) {
                    usleep(1_000);
                }
            }
        } while ($running > 0 && microtime(true) < $deadline && $multiStatus === CURLM_OK);

        foreach ($transfers as $entry) {
            $responses[$entry['key']] = HttpResponse::error(
                $entry['transfer']->request->url,
                'Request timed out or exceeded the total search budget',
                (microtime(true) - $entry['started']) * 1000
            );
            curl_multi_remove_handle($multi, $entry['handle']);
        }

        curl_multi_close($multi);

        $ordered = [];
        foreach (array_keys($requests) as $key) {
            if (isset($responses[$key])) {
                $ordered[$key] = $responses[$key];
            }
        }

        return $ordered;
    }

    public function fetch(HttpRequest $request): HttpResponse
    {
        $deadline = microtime(true) + ($request->timeoutMs / 1000) + 0.1;
        $responses = $this->fetchAll(['single' => $request], $deadline);

        return $responses['single'] ?? HttpResponse::error($request->url, 'No response');
    }

    private function makeHandle(HttpRequest $request, CurlTransfer $transfer, int $timeoutMs): \CurlHandle
    {
        $handle = curl_init();

        if ($handle === false) {
            throw new \RuntimeException('Unable to create a cURL handle');
        }

        $headers = $request->headers;
        $userAgent = $headers['User-Agent'] ?? $headers['user-agent'] ?? $this->config->get('http.user_agent');
        unset($headers['User-Agent'], $headers['user-agent']);

        $headerLines = ['Expect:'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => (int) $this->config->get('http.max_redirects', 3),
            CURLOPT_CONNECTTIMEOUT_MS => min(
                $timeoutMs,
                (int) $this->config->get('http.connect_timeout_ms', 800)
            ),
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => (string) $userAgent,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SHARE => $this->share,
            CURLOPT_HEADERFUNCTION => static fn ($ch, string $line): int => $transfer->onHeader($line),
            CURLOPT_WRITEFUNCTION => static fn ($ch, string $chunk): int => $transfer->onWrite($chunk),
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        if ($request->method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $request->body ?? '';
        } elseif ($request->method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $request->method;
            if ($request->body !== null) {
                $options[CURLOPT_POSTFIELDS] = $request->body;
            }
        }

        curl_setopt_array($handle, $options);

        return $handle;
    }

    private function buildResponse(\CurlHandle $handle, CurlTransfer $transfer, float $started): HttpResponse
    {
        $errorNumber = curl_errno($handle);
        $error = $errorNumber !== 0 ? curl_error($handle) : null;

        if ($transfer->tooLarge) {
            $error = 'Response exceeded the configured maximum size';
        }

        $status = $transfer->status;
        if ($status === 0) {
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        }

        $effectiveUrl = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        if (!is_string($effectiveUrl) || $effectiveUrl === '') {
            $effectiveUrl = $transfer->request->url;
        }

        $ms = (microtime(true) - $started) * 1000;

        return new HttpResponse(
            $status,
            $transfer->headers,
            $transfer->body,
            $effectiveUrl,
            round($ms, 1),
            $error
        );
    }
}
