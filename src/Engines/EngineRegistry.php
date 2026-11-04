<?php

declare(strict_types=1);

namespace SearPHP\Engines;

use SearPHP\Config;

final class EngineRegistry
{
    /** @var array<string, EngineInterface> */
    private array $engines = [];

    public function __construct(private readonly Config $config)
    {
        $classes = [
            'duckduckgo' => DuckDuckGoWeb::class,
            'duckduckgo_lite' => DuckDuckGoLite::class,
            'bing' => BingWeb::class,
            'brave' => BraveWeb::class,
            'wikipedia' => Wikipedia::class,
            'ddg_instant' => DuckDuckGoInstantAnswer::class,
            'duckduckgo_images' => DuckDuckGoImages::class,
            'bing_images' => BingImages::class,
            'brave_images' => BraveImages::class,
        ];

        foreach ($classes as $id => $class) {
            /** @var EngineInterface $engine */
            $engine = new $class($this->config);
            $this->engines[$id] = $engine;
        }
    }

    public function get(string $id): ?EngineInterface
    {
        return $this->engines[$id] ?? null;
    }

    public function isEnabled(string $id): bool
    {
        if (!isset($this->engines[$id])) {
            return false;
        }

        return (bool) $this->config->get('engines.' . $id . '.enabled', true);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->engines);
    }

    /**
     * @return list<string>
     */
    public function enabledIds(): array
    {
        return array_values(array_filter($this->ids(), fn (string $id): bool => $this->isEnabled($id)));
    }

    /**
     * @return list<string>
     */
    public function defaultWebIds(): array
    {
        $ids = (array) $this->config->get('engine_defaults.web', []);
        $result = [];

        foreach ($ids as $id) {
            if (is_string($id) && $this->isEnabled($id)) {
                $engine = $this->engines[$id] ?? null;
                if ($engine !== null && in_array('web', $engine->categories(), true)) {
                    $result[] = $id;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @return list<string>
     */
    public function defaultImageIds(): array
    {
        $ids = (array) $this->config->get('engine_defaults.images', []);
        $result = [];

        foreach ($ids as $id) {
            if (is_string($id) && $this->isEnabled($id)) {
                $engine = $this->engines[$id] ?? null;
                if ($engine !== null && in_array('images', $engine->categories(), true)) {
                    $result[] = $id;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param list<string> $ids
     * @return array<string, float>
     */
    public function weights(array $ids): array
    {
        $weights = [];
        foreach ($ids as $id) {
            $engine = $this->engines[$id] ?? null;
            if ($engine !== null) {
                $weights[$id] = $engine->weight();
            }
        }

        return $weights;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function metadata(): array
    {
        $metadata = [];
        foreach ($this->engines as $id => $engine) {
            $metadata[] = [
                'id' => $id,
                'label' => $engine->label(),
                'categories' => $engine->categories(),
                'weight' => $engine->weight(),
                'enabled' => $this->isEnabled($id),
                'timeout_ms' => (int) $this->config->get('engines.' . $id . '.timeout_ms', 1800),
            ];
        }

        return $metadata;
    }
}
