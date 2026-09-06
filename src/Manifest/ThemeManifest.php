<?php

namespace Rawbinn\Themes\Manifest;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Rawbinn\Themes\Exceptions\FileMissingException;
use Rawbinn\Themes\Support\ThemeRegistry;

class ThemeManifest
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $runtimeCache = [];

    public function __construct(
        protected Filesystem $files,
        protected Repository $config,
        protected ThemeRegistry $registry,
        protected ?CacheRepository $cache = null,
    ) {}

    public function flushRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    public function forget(string $theme): void
    {
        $theme = strtolower($theme);
        unset($this->runtimeCache[$theme]);

        if ($this->shouldUsePersistentCache()) {
            $this->cache?->forget($this->cacheKey($theme));
        }
    }

    public function flushPersistentCache(): void
    {
        if (! $this->shouldUsePersistentCache()) {
            return;
        }

        foreach ($this->registry->all() as $theme) {
            $this->cache?->forget($this->cacheKey($theme));
        }
    }

    public function warm(): int
    {
        $count = 0;

        foreach ($this->registry->all() as $theme) {
            if ($this->registry->isValid($theme)) {
                $this->get($theme);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $theme): array
    {
        $theme = strtolower($theme);

        if ($this->config->get('themes.cache_manifest', true) && array_key_exists($theme, $this->runtimeCache)) {
            return $this->runtimeCache[$theme];
        }

        if ($this->shouldUsePersistentCache()) {
            $cached = $this->cache?->get($this->cacheKey($theme));

            if (is_array($cached)) {
                return $this->runtimeCache[$theme] = $cached;
            }
        }

        if (! $this->registry->exists($theme)) {
            return $this->runtimeCache[$theme] = [];
        }

        $path = $this->registry->getJsonPath($theme);

        if (! $this->files->exists($path)) {
            return $this->runtimeCache[$theme] = [];
        }

        $json = json_decode($this->files->get($path), true);
        $manifest = is_array($json) ? $json : [];

        if ($this->shouldUsePersistentCache()) {
            $this->cache?->put(
                $this->cacheKey($theme),
                $manifest,
                (int) $this->config->get('themes.manifest_cache_ttl', 3600)
            );
        }

        return $this->runtimeCache[$theme] = $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStrict(string $theme): array
    {
        $manifest = $this->get($theme);

        if ($manifest !== []) {
            return $manifest;
        }

        if ($this->registry->exists($theme) && ! $this->files->exists($this->registry->getJsonPath($theme))) {
            throw new FileMissingException("Theme [{$theme}] must have a valid theme.json manifest file.");
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function put(string $theme, array $content): int|false
    {
        $written = $this->files->put(
            $this->registry->getJsonPath($theme),
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->forget($theme);

        return $written;
    }

    public function getProperty(string $property, mixed $default = null): mixed
    {
        [$theme, $key] = $this->parseProperty($property);

        return data_get($this->getStrict($theme), $key, $default);
    }

    public function setProperty(string $property, mixed $value): bool
    {
        [$theme, $key] = $this->parseProperty($property);

        $content = $this->getStrict($theme);
        $content[$key] = $value;

        $this->put($theme, $content);

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseProperty(string $property): array
    {
        $parts = explode('::', $property);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("Property must be in the format 'theme::key', got: {$property}");
        }

        return [$parts[0], $parts[1]];
    }

    protected function shouldUsePersistentCache(): bool
    {
        return filled($this->config->get('themes.manifest_cache_store'));
    }

    protected function cacheKey(string $theme): string
    {
        $prefix = (string) $this->config->get('themes.manifest_cache_key', 'rawbinn.themes.manifest');

        return "{$prefix}.{$theme}";
    }
}
