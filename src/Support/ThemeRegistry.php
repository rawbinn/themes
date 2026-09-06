<?php

namespace Rawbinn\Themes\Support;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class ThemeRegistry
{
    protected ?string $path = null;

    public function __construct(
        protected Filesystem $files,
        protected Repository $config,
    ) {}

    public function getPath(): string
    {
        return $this->path ?: (string) $this->config->get('themes.paths.absolute');
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getThemePath(string $theme): string
    {
        return rtrim($this->getPath(), '/\\').'/'.trim($theme, '/').'/';
    }

    public function getJsonPath(string $theme): string
    {
        return $this->getThemePath($theme).'theme.json';
    }

    public function getViewsPath(string $theme): string
    {
        return $this->getThemePath($theme).'views';
    }

    public function getAssetsPath(string $theme): string
    {
        return $this->getThemePath($theme).trim((string) $this->config->get('themes.paths.assets'), '/').'/';
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $themes = [];
        $path = $this->getPath();

        if (! $this->files->isDirectory($path)) {
            return $themes;
        }

        foreach ($this->files->directories($path) as $directory) {
            $themes[] = basename($directory);
        }

        return $themes;
    }

    public function exists(string $theme): bool
    {
        return in_array($theme, $this->all(), true);
    }

    public function isValid(string $theme): bool
    {
        if (! $this->exists($theme)) {
            return false;
        }

        return $this->files->exists($this->getJsonPath($theme))
            && $this->files->isDirectory($this->getViewsPath($theme));
    }

    /**
     * @return list<string>
     */
    public function allValid(): array
    {
        return array_values(array_filter($this->all(), fn (string $theme) => $this->isValid($theme)));
    }

    public function assetExists(string $theme, string $asset): bool
    {
        return $this->files->exists($this->getAssetsPath($theme).ltrim($asset, '/'));
    }
}
