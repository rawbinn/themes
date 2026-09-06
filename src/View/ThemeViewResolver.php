<?php

namespace Rawbinn\Themes\View;

use Closure;
use Illuminate\View\Factory as ViewFactory;
use Rawbinn\Themes\Manifest\ThemeManifest;

class ThemeViewResolver
{
    public function __construct(
        protected ViewFactory $viewFactory,
        protected ThemeManifest $manifest,
        protected Closure $activeThemeResolver,
    ) {}

    public function resolve(string $view): string
    {
        $activeTheme = ($this->activeThemeResolver)();

        if (! is_string($activeTheme) || $activeTheme === '') {
            return $view;
        }

        $parent = $this->manifest->get($activeTheme)['parent'] ?? null;

        $candidates = [
            $this->namespace($view, $activeTheme),
            is_string($parent) && $parent !== '' ? $this->namespace($view, $parent) : null,
            $view,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $this->viewFactory->exists($candidate)) {
                return $candidate;
            }
        }

        return $view;
    }

    public function exists(string $view): bool
    {
        return $this->viewFactory->exists($this->resolve($view));
    }

    protected function namespace(string $view, string $theme): string
    {
        return $theme."::{$view}";
    }
}
