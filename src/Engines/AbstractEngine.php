<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use DOMDocument;
use DOMNode;
use DOMXPath;
use SearPHP\Config;

abstract class AbstractEngine implements EngineInterface
{
    public function __construct(protected readonly Config $config)
    {
    }

    public function label(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->id()));
    }

    public function weight(): float
    {
        return (float) $this->config->get('engines.' . $this->id() . '.weight', 1.0);
    }

    public function paginationSupported(): bool
    {
        return true;
    }

    public function pageSize(): int
    {
        return 10;
    }

    protected function timeout(): int
    {
        return (int) $this->config->get('engines.' . $this->id() . '.timeout_ms', 1800);
    }

    protected function htmlXPath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    protected function text(?DOMNode $node): string
    {
        if (!$node instanceof DOMNode) {
            return '';
        }

        return $this->clean($node->textContent);
    }

    protected function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected function langKl(string $lang): string
    {
        $map = [
            'en' => 'us-en',
            'en-us' => 'us-en',
            'en-gb' => 'uk-en',
            'en-ca' => 'ca-en',
            'en-au' => 'au-en',
            'de' => 'de-de',
            'fr' => 'fr-fr',
            'es' => 'es-es',
            'it' => 'it-it',
            'pt' => 'pt-pt',
            'pt-br' => 'br-pt',
            'nl' => 'nl-nl',
            'sv' => 'se-sv',
            'da' => 'dk-da',
            'fi' => 'fi-fi',
            'no' => 'no-no',
            'pl' => 'pl-pl',
            'ru' => 'ru-ru',
            'tr' => 'tr-tr',
            'ja' => 'jp-jp',
            'ko' => 'kr-kr',
            'zh' => 'cn-zh',
            'zh-cn' => 'cn-zh',
            'zh-tw' => 'tw-tzh',
            'ar' => 'xa-ar',
            'hi' => 'in-en',
        ];

        return $map[strtolower($lang)] ?? 'us-en';
    }

    protected function timeFilter(?string $timeRange): string
    {
        return match ($timeRange) {
            'day' => 'd',
            'week' => 'w',
            'month' => 'm',
            'year' => 'y',
            default => '',
        };
    }

    protected function browserHeaders(string $userAgent, string $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', ?string $referer = null): array
    {
        $headers = [
            'User-Agent' => $userAgent,
            'Accept' => $accept,
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];

        if ($referer !== null) {
            $headers['Referer'] = $referer;
        }

        return $headers;
    }

    protected function ddgUserAgent(): string
    {
        return (string) $this->config->get('http.ddg_user_agent', $this->config->get('http.user_agent'));
    }

    protected function ddgFallbackUserAgent(): string
    {
        return (string) $this->config->get('http.ddg_fallback_user_agent', 'Lynx/2.8.9rel.1 libwww-FM/2.14 SSL-MM/1.4.1');
    }

    protected function browserUserAgent(): string
    {
        return (string) $this->config->get('http.browser_user_agent', $this->config->get('http.user_agent'));
    }
}
