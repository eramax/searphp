<?php

declare(strict_types=1);

namespace SearPHP\Format;

use SearPHP\Search\Query;
use SearPHP\Search\SearchResultSet;

final class MarkdownFormatter
{
    public function format(SearchResultSet $set, Query $query): string
    {
        $lines = [];
        $lines[] = '# ' . $this->inline($set->query, 240);
        $lines[] = '';

        $meta = [];
        $meta[] = count($set->results) . ' results';
        if ($set->images !== []) {
            $meta[] = count($set->images) . ' images';
        }
        $meta[] = $set->cached ? 'cached' : $set->tookMs . ' ms';
        $engines = $this->activeEngines($set);
        if ($engines !== []) {
            $meta[] = implode(', ', $engines);
        }
        if ($set->partial) {
            $meta[] = 'partial';
        }
        $lines[] = '> ' . implode(' · ', $meta);
        $lines[] = '';

        if ($set->answers !== []) {
            $lines[] = '## Instant answers';
            $lines[] = '';
            foreach ($set->answers as $answer) {
                $text = $this->block((string) ($answer['text'] ?? ''), 1200);
                if ($text === '') {
                    continue;
                }
                $source = trim((string) ($answer['source'] ?? ''));
                $label = $source !== '' ? '**' . $this->inline($source, 80) . ':** ' : '';
                $lines[] = '- ' . $label . $this->inline($text, 800);
            }
            $lines[] = '';
        }

        if ($set->results !== []) {
            $lines[] = '## Results';
            $lines[] = '';
            foreach ($set->results as $index => $result) {
                $url = $this->url($result->url);
                if ($url === null) {
                    continue;
                }
                $title = $this->inline($result->title !== '' ? $result->title : $url, 200);
                $lines[] = ($index + 1) . '. [' . $title . '](<' . $url . '>)';

                if ($result->snippet !== '') {
                    $lines[] = '   ' . $this->inline($result->snippet, 320);
                }

                $badges = array_map(
                    static fn (string $engine): string => '`' . $engine . '`',
                    $result->engines
                );
                if ($result->publishedAt !== null && $result->publishedAt !== '') {
                    $badges[] = $this->inline($result->publishedAt, 40);
                }
                if ($badges !== []) {
                    $lines[] = '   ' . implode(' · ', $badges);
                }
                $lines[] = '';
            }
        }

        if ($set->images !== []) {
            $lines[] = '## Images';
            $lines[] = '';
            foreach ($set->images as $index => $image) {
                $imageUrl = $this->url($image->imageUrl);
                if ($imageUrl === null) {
                    continue;
                }
                $alt = $this->inline($image->title !== '' ? $image->title : 'image', 120);
                $lines[] = ($index + 1) . '. ![' . $alt . '](<' . $imageUrl . '>)';
                $details = [];
                $pageUrl = $this->url($image->pageUrl);
                if ($pageUrl !== null && $pageUrl !== $imageUrl) {
                    $details[] = '[source](<' . $pageUrl . '>)';
                }
                if ($image->width !== null && $image->height !== null) {
                    $details[] = $image->width . '×' . $image->height;
                }
                if ($details !== []) {
                    $lines[] = '   ' . implode(' · ', $details);
                }
                $lines[] = '';
            }
        }

        if ($set->suggestions !== []) {
            $lines[] = '## Related searches';
            $lines[] = '';
            foreach ($set->suggestions as $suggestion) {
                $lines[] = '- ' . $this->inline($suggestion, 160);
            }
            $lines[] = '';
        }

        if ($set->partial) {
            $lines[] = '## Engine status';
            $lines[] = '';
            foreach ($set->engineStatus as $engine => $status) {
                if (in_array($status['status'], ['ok', 'empty', 'disabled'], true)) {
                    continue;
                }
                $line = '- `' . $engine . '`: ' . $status['status']
                    . ' (' . $status['count'] . ' results, ' . $status['ms'] . ' ms)';
                if (isset($status['error']) && $status['error'] !== '') {
                    $line .= ' — ' . $this->inline($status['error'], 200);
                }
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private function inline(string $value, int $limit): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') > $limit) {
            $value = mb_substr($value, 0, max(1, $limit - 1), 'UTF-8') . '…';
        }

        return strtr($value, [
            '\\' => '\\\\',
            '`' => '\\`',
            '*' => '\\*',
            '_' => '\\_',
            '[' => '\\[',
            ']' => '\\]',
            '<' => '&lt;',
            '>' => '&gt;',
        ]);
    }

    private function block(string $value, int $limit): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;
        // Keep model-generated Markdown (lists, emphasis), but do not pass raw
        // HTML or dangerous link schemes through to a Markdown renderer.
        $value = str_replace(['<', '>'], ['&lt;', '&gt;'], $value);
        $value = preg_replace(
            '/\]\(\s*(?!(?:https?):)[a-z][a-z0-9+.-]*:[^)]*\)/i',
            ']',
            $value
        ) ?? $value;
        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') > $limit) {
            $value = mb_substr($value, 0, $limit - 1, 'UTF-8') . '…';
        }

        return $value;
    }

    private function url(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('~^https?://~i', $value) !== 1) {
            return null;
        }

        return str_replace(['<', '>', ' '], ['%3C', '%3E', '%20'], $value);
    }

    /**
     * @return list<string>
     */
    private function activeEngines(SearchResultSet $set): array
    {
        $engines = [];
        foreach ($set->engineStatus as $engine => $status) {
            if ($status['status'] !== 'disabled') {
                $engines[] = $engine;
            }
        }

        return $engines;
    }
}
