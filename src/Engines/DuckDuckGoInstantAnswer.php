<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Http\HttpRequest;
use SearPHP\Http\HttpResponse;
use SearPHP\Search\EngineOutcome;
use SearPHP\Search\Query;
use SearPHP\Search\Result;

final class DuckDuckGoInstantAnswer extends AbstractEngine
{
    public function id(): string
    {
        return 'ddg_instant';
    }

    public function label(): string
    {
        return 'DuckDuckGo Instant Answers';
    }

    public function categories(): array
    {
        return ['web'];
    }

    public function request(Query $query): ?HttpRequest
    {
        $params = [
            'q' => $query->q,
            'format' => 'json',
            'no_html' => '1',
            'skip_disambig' => '1',
            't' => 'searphp',
        ];

        return HttpRequest::get(
            'https://api.duckduckgo.com/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $this->browserHeaders(
                $this->browserUserAgent(),
                'application/json,text/plain,*/*;q=0.8'
            ),
            $this->timeout(),
            $this->id()
        );
    }

    public function parse(HttpResponse $response, Query $query): EngineOutcome
    {
        if ($response->error !== null) {
            return new EngineOutcome(status: 'error', error: $response->error, ms: (int) $response->ms);
        }

        if (!$response->ok()) {
            return new EngineOutcome(
                status: $response->isRateLimited() ? 'rate_limited' : 'error',
                error: 'HTTP ' . $response->status . ' from DuckDuckGo Instant Answers',
                ms: (int) $response->ms
            );
        }

        $data = $response->json();
        if (!is_array($data)) {
            return new EngineOutcome(status: 'error', error: 'Invalid Instant Answer JSON', ms: (int) $response->ms);
        }

        $answers = [];
        $results = [];

        $abstract = trim((string) ($data['AbstractText'] ?? ''));
        if ($abstract !== '') {
            $answers[] = [
                'text' => $abstract,
                'source' => (string) ($data['AbstractSource'] ?? 'DuckDuckGo'),
                'url' => (string) ($data['AbstractURL'] ?? ''),
            ];
        }

        $answer = trim((string) ($data['Answer'] ?? ''));
        if ($answer !== '') {
            $answers[] = [
                'text' => $answer,
                'source' => (string) ($data['AnswerType'] ?? 'DuckDuckGo'),
            ];
        }

        $definition = trim((string) ($data['Definition'] ?? ''));
        if ($definition !== '') {
            $answers[] = [
                'text' => $definition,
                'source' => (string) ($data['DefinitionSource'] ?? 'DuckDuckGo'),
                'url' => (string) ($data['DefinitionURL'] ?? ''),
            ];
        }

        $topics = $this->flattenTopics($data['RelatedTopics'] ?? []);
        foreach ($topics as $topic) {
            $text = trim((string) ($topic['Text'] ?? ''));
            $url = trim((string) ($topic['FirstURL'] ?? ''));
            if ($text === '' || $url === '') {
                continue;
            }

            $title = mb_substr($text, 0, 120);
            $results[] = new Result($title, $url, $text, $this->id(), count($results) + 1);
        }

        $status = ($answers === [] && $results === []) ? 'empty' : 'ok';

        return new EngineOutcome(
            results: $results,
            answers: $this->dedupeAnswers($answers),
            status: $status,
            ms: (int) $response->ms
        );
    }

    /**
     * @param mixed $topics
     * @return list<array<string, mixed>>
     */
    private function flattenTopics(mixed $topics): array
    {
        if (!is_array($topics)) {
            return [];
        }

        $flat = [];
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            if (isset($topic['Topics']) && is_array($topic['Topics'])) {
                foreach ($this->flattenTopics($topic['Topics']) as $child) {
                    $flat[] = $child;
                }
                continue;
            }
            if (isset($topic['FirstURL'])) {
                $flat[] = $topic;
            }
        }

        return $flat;
    }

    /**
     * @param list<array{text: string, source?: string, url?: string}> $answers
     * @return list<array{text: string, source?: string, url?: string}>
     */
    private function dedupeAnswers(array $answers): array
    {
        $seen = [];
        $unique = [];
        foreach ($answers as $answer) {
            $key = mb_strtolower($answer['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $answer;
        }

        return $unique;
    }
}
