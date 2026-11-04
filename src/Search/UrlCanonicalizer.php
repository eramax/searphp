<?php

declare(strict_types=1);

namespace SearPHP\Search;

final class UrlCanonicalizer
{
    private const TRACKING_EXACT = [
        'fbclid', 'gclid', 'dclid', 'msclkid', 'yclid', 'twclid', 'igshid',
        'mc_cid', 'mc_eid', 'ref_src', 'ref_url', 'spm', 'si', 'feature',
        'trk', 'trkcampaign', 'sc_cid', '_ga', '_gl', 'mkt_tok', 'vero_id',
        'oly_anon_id', 'oly_enc_id', 'wickedid', 's_cid',
    ];

    public static function normalize(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return null;
        }

        $url = str_replace(' ', '%20', $url);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || str_contains($host, '..')) {
            return null;
        }

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $host = strtolower($ascii);
            }
        }

        if (preg_match('/^\[[0-9a-f:.]+\]$/i', $host) !== 1 && preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $query = self::normalizeQuery((string) ($parts['query'] ?? ''), false);

        $authority = $host . ($port !== null ? ':' . $port : '');

        return $scheme . '://' . $authority . $path . ($query !== '' ? '?' . $query : '');
    }

    public static function key(string $url): ?string
    {
        $normalized = self::normalize($url);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = self::normalizeQuery((string) ($parts['query'] ?? ''), true);

        return $host . $path . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Resolve the redirect wrappers used by search engines.
     */
    public static function fromRedirect(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return self::normalize($url);
        }

        $host = strtolower((string) $parts['host']);
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (str_ends_with($host, 'duckduckgo.com')) {
            $target = $query['uddg'] ?? null;
            if (is_string($target) && $target !== '') {
                return self::normalize(rawurldecode($target));
            }
        }

        if (str_ends_with($host, 'bing.com') && str_starts_with((string) ($parts['path'] ?? ''), '/ck/')) {
            $target = $query['u'] ?? null;
            if (is_string($target) && $target !== '') {
                if (str_starts_with($target, 'a1')) {
                    $decoded = self::base64UrlDecode(substr($target, 2));
                    if ($decoded !== null) {
                        return self::normalize($decoded);
                    }
                }

                $decoded = rawurldecode($target);
                if (str_starts_with($decoded, 'http')) {
                    return self::normalize($decoded);
                }
            }
        }

        if (str_ends_with($host, 'google.com') && str_starts_with((string) ($parts['path'] ?? ''), '/url')) {
            $target = $query['q'] ?? $query['url'] ?? null;
            if (is_string($target) && $target !== '') {
                return self::normalize(rawurldecode($target));
            }
        }

        return self::normalize($url);
    }

    private static function normalizeQuery(string $query, bool $sort): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = rawurldecode($rawKey);
            $value = rawurldecode($rawValue);

            if ($key === '' || self::isTracking($key)) {
                continue;
            }

            $pairs[] = [$key, $value];
        }

        if ($sort) {
            usort($pairs, static function (array $a, array $b): int {
                return strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]);
            });
        }

        return implode('&', array_map(
            static fn (array $pair): string => rawurlencode($pair[0]) . '=' . rawurlencode($pair[1]),
            $pairs
        ));
    }

    private static function isTracking(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::TRACKING_EXACT, true)) {
            return true;
        }

        foreach (['utm_', 'pk_', 'mtm_', 'hsa_', 'at_', 'vero_', 'oly_'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $value = strtr($value, '-_', '+/');
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
