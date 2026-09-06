<?php

namespace Rawbinn\Themes\Assets;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Vite;
use Rawbinn\Themes\Exceptions\ThemeNotFoundException;
use Rawbinn\Themes\Manifest\ThemeManifest;
use Rawbinn\Themes\Support\ThemeRegistry;

class ThemeAssetResolver
{
    public function __construct(
        protected Repository $config,
        protected Filesystem $files,
        protected ThemeRegistry $registry,
        protected ThemeManifest $manifest,
        protected Closure $activeThemeResolver,
    ) {}

    public function asset(string $asset): string
    {
        [$theme, $asset] = $this->parseAssetReference($asset);
        $theme = $this->resolveThemeSlug($theme);
        $asset = ltrim($asset, '/');
        $theme = $this->resolveThemeForAsset($theme, $asset);

        return asset($this->buildPublicPath($theme, $asset));
    }

    public function secureAsset(string $asset): string
    {
        return preg_replace('/^http:/i', 'https:', $this->asset($asset));
    }

    /**
     * @param  array<int, string>|string  $entrypoints
     */
    public function vite(array|string $entrypoints, ?string $theme = null): string
    {
        if (! class_exists(Vite::class)) {
            throw new \RuntimeException('Laravel Vite integration is not available.');
        }

        $theme = $this->resolveThemeSlug($theme);
        $entrypoints = is_array($entrypoints) ? $entrypoints : [$entrypoints];
        $buildDirectory = $this->viteBuildDirectory($theme);
        $hotFile = $this->viteHotFile($theme);

        /** @var Vite $vite */
        $vite = app(Vite::class);

        return $vite
            ->useHotFile($hotFile)
            ->useBuildDirectory($buildDirectory)
            ->withEntryPoints($entrypoints);
    }

    public function mix(string $path, ?string $theme = null): string
    {
        $theme = $this->resolveThemeSlug($theme);
        $path = ltrim($path, '/');
        $theme = $this->resolveThemeForMixManifest($theme);

        $manifestPath = $this->registry->getThemePath($theme).'mix-manifest.json';
        $manifest = $this->readJsonFile($manifestPath);

        if (! array_key_exists($path, $manifest)) {
            throw new \InvalidArgumentException("Unable to locate Mix file: {$path}.");
        }

        $mixPath = ltrim((string) $manifest[$path], '/');

        return asset($this->buildPublicPath($theme, $mixPath));
    }

    protected function resolveThemeSlug(?string $theme): string
    {
        $theme = $theme ?: ($this->activeThemeResolver)();

        if (! is_string($theme) || $theme === '') {
            throw new ThemeNotFoundException('No active theme is set.');
        }

        return $theme;
    }

    protected function resolveThemeForAsset(string $theme, string $asset): string
    {
        if ($this->registry->assetExists($theme, $asset)) {
            return $theme;
        }

        $parent = $this->manifest->get($theme)['parent'] ?? null;

        if (is_string($parent) && $parent !== '' && $this->registry->assetExists($parent, $asset)) {
            return $parent;
        }

        return $theme;
    }

    protected function resolveThemeForMixManifest(string $theme): string
    {
        if ($this->files->exists($this->registry->getThemePath($theme).'mix-manifest.json')) {
            return $theme;
        }

        $parent = $this->manifest->get($theme)['parent'] ?? null;

        if (is_string($parent) && $parent !== '' && $this->files->exists($this->registry->getThemePath($parent).'mix-manifest.json')) {
            return $parent;
        }

        return $theme;
    }

    protected function buildPublicPath(string $theme, string $asset): string
    {
        return trim((string) $this->config->get('themes.paths.base'), '/').'/'
            .$theme.'/'
            .trim((string) $this->config->get('themes.paths.assets'), '/').'/'
            .$asset;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected function parseAssetReference(string $asset): array
    {
        $segments = explode('::', $asset);

        if (count($segments) === 2) {
            return [$segments[0], $segments[1]];
        }

        return [null, $segments[0]];
    }

    protected function viteBuildDirectory(string $theme): string
    {
        $manifest = $this->manifest->get($theme);
        $buildDirectory = $manifest['vite']['build_directory']
            ?? $this->config->get('themes.vite.build_directory', 'build');

        return trim((string) $this->config->get('themes.paths.base'), '/').'/'
            .$theme.'/'
            .trim((string) $buildDirectory, '/');
    }

    protected function viteHotFile(string $theme): string
    {
        $manifest = $this->manifest->get($theme);
        $hotFile = $manifest['vite']['hot_file']
            ?? $this->config->get('themes.vite.hot_file', 'theme.hot');

        return public_path(trim((string) $this->config->get('themes.paths.base'), '/').'/'.$theme.'/'.ltrim((string) $hotFile, '/'));
    }

    /**
     * @return array<string, string>
     */
    protected function readJsonFile(string $path): array
    {
        if (! $this->files->exists($path)) {
            throw new \InvalidArgumentException("Mix manifest [{$path}] does not exist.");
        }

        $json = json_decode($this->files->get($path), true);

        return is_array($json) ? $json : [];
    }
}
