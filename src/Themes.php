<?php

namespace Rawbinn\Themes;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\View;
use Rawbinn\Themes\Assets\ThemeAssetResolver;
use Rawbinn\Themes\Events\ThemeActivated;
use Rawbinn\Themes\Events\ThemeBooted;
use Rawbinn\Themes\Events\ThemeRegistered;
use Rawbinn\Themes\Exceptions\FileMissingException;
use Rawbinn\Themes\Exceptions\ThemeNotFoundException;
use Rawbinn\Themes\Manifest\ThemeManifest;
use Rawbinn\Themes\Support\ThemeRegistry;
use Rawbinn\Themes\View\ThemeViewResolver;

class Themes
{
    protected ?string $active = null;

    protected ?string $layout = null;

    /**
     * @var array<string, bool>
     */
    protected array $bootedThemes = [];

    protected ThemeViewResolver $viewResolver;

    protected ThemeAssetResolver $assetResolver;

    public function __construct(
        protected ThemeRegistry $registry,
        protected ThemeManifest $manifest,
        protected Filesystem $files,
        protected Repository $config,
        protected ViewFactory $viewFactory,
        protected ?Dispatcher $events = null,
    ) {
        $this->viewResolver = new ThemeViewResolver(
            $this->viewFactory,
            $this->manifest,
            fn () => $this->getActive(),
        );

        $this->assetResolver = new ThemeAssetResolver(
            $this->config,
            $this->files,
            $this->registry,
            $this->manifest,
            fn () => $this->getActive(),
        );
    }

    public function flushState(): void
    {
        $this->active = null;
        $this->layout = null;
        $this->bootedThemes = [];
        $this->manifest->flushRuntimeCache();
    }

    public function register(): void
    {
        foreach ($this->registry->all() as $theme) {
            $this->registerNamespace($theme);
        }
    }

    public function registerNamespace(string $theme): void
    {
        $viewsPath = $this->registry->getViewsPath($theme);

        if ($this->files->isDirectory($viewsPath)) {
            $this->viewFactory->addNamespace($theme, $viewsPath);
            $this->dispatch(new ThemeRegistered($theme));
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * @return list<string>
     */
    public function allValid(): array
    {
        return $this->registry->allValid();
    }

    public function exists(string $theme): bool
    {
        return $this->registry->exists($theme);
    }

    public function isValid(string $theme): bool
    {
        return $this->registry->isValid($theme);
    }

    public function getPath(): string
    {
        return $this->registry->getPath();
    }

    public function setPath(string $path): self
    {
        $this->registry->setPath($path);

        return $this;
    }

    public function getActive(): ?string
    {
        if ($this->active) {
            return $this->active;
        }

        $resolver = $this->config->get('themes.active_resolver');

        if (is_callable($resolver)) {
            $resolved = $resolver();

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        $configured = $this->config->get('themes.active');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    public function getExplicitActive(): ?string
    {
        return $this->active;
    }

    public function setActive(string $theme): self
    {
        if (! $this->registry->exists($theme)) {
            throw new ThemeNotFoundException("Theme [{$theme}] does not exist.");
        }

        $previous = $this->active;
        $this->active = $theme;
        $this->bootTheme($theme);

        if ($previous !== $theme) {
            $this->dispatch(new ThemeActivated($theme, $previous));
        }

        return $this;
    }

    public function bootTheme(?string $theme = null): void
    {
        $theme = $theme ?? $this->getActive();

        if (! is_string($theme) || $theme === '' || isset($this->bootedThemes[$theme])) {
            return;
        }

        if (! $this->registry->exists($theme)) {
            return;
        }

        $bootFile = (string) $this->config->get('themes.boot_file', 'functions.php');
        $path = $this->registry->getThemePath($theme).$bootFile;

        if ($this->files->exists($path)) {
            require_once $path;
        }

        $this->bootedThemes[$theme] = true;
        $this->dispatch(new ThemeBooted($theme));
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): self
    {
        $this->layout = $this->getView($layout);

        return $this;
    }

    public function getView(string $view): string
    {
        return $this->viewResolver->resolve($view);
    }

    public function view(string $view, array $data = []): View
    {
        if (! is_null($this->layout)) {
            $data['theme_layout'] = $this->getLayout();
        }

        return $this->viewFactory->make($this->getView($view), $data);
    }

    public function viewExists(string $view): bool
    {
        return $this->viewResolver->exists($view);
    }

    public function response(string $view, array $data = [], int $status = 200, array $headers = []): Response
    {
        return new Response($this->view($view, $data)->render(), $status, $headers);
    }

    public function getThemePath(string $theme): string
    {
        return $this->registry->getThemePath($theme);
    }

    /**
     * @return array<int, \SplFileInfo>
     */
    public function getThemeFiles(?string $theme = null): array
    {
        $theme = $theme ?? $this->getActive();

        if (! is_string($theme) || $theme === '') {
            throw new ThemeNotFoundException('No active theme is set.');
        }

        $path = $this->registry->getThemePath($theme);

        if (! $this->files->isDirectory($path)) {
            throw new FileMissingException("Theme [{$theme}] does not exist.");
        }

        return $this->files->allFiles($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(string $theme): array
    {
        return $this->manifest->get($theme);
    }

    public function warmManifestCache(): int
    {
        return $this->manifest->warm();
    }

    public function clearManifestCache(): void
    {
        $this->manifest->flushPersistentCache();
        $this->manifest->flushRuntimeCache();
    }

    /**
     * @deprecated Use manifest() and read the "packages" key from theme.json instead.
     *
     * @return array<string, mixed>
     */
    public function getFunctionJsonContents(string $theme): array
    {
        $manifest = $this->manifest($theme);

        if (isset($manifest['packages']) && is_array($manifest['packages'])) {
            return ['packages' => $manifest['packages']];
        }

        $path = $this->getThemeFunctionPath($theme);

        if (! $this->files->exists($path)) {
            return [];
        }

        $json = json_decode($this->files->get($path), true);

        return is_array($json) ? $json : [];
    }

    /**
     * @deprecated Use manifest() instead.
     */
    public function getFunctionProperty(string $theme, ?string $key = null, mixed $default = null): mixed
    {
        return Arr::get($this->getFunctionJsonContents($theme), $key, $default);
    }

    public function getThemeFunctionPath(string $theme): string
    {
        return $this->registry->getThemePath($theme).'functions.json';
    }

    public function getJsonPath(string $theme): string
    {
        return $this->registry->getJsonPath($theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonContents(string $theme): array
    {
        return $this->manifest->getStrict($theme);
    }

    public function setJsonContents(string $theme, array $content): int|false
    {
        return $this->manifest->put($theme, $content);
    }

    public function getProperty(string $property, mixed $default = null): mixed
    {
        return $this->manifest->getProperty($property, $default);
    }

    public function setProperty(string $property, mixed $value): bool
    {
        return $this->manifest->setProperty($property, $value);
    }

    public function asset(string $asset): string
    {
        return $this->assetResolver->asset($asset);
    }

    public function secureAsset(string $asset): string
    {
        return $this->assetResolver->secureAsset($asset);
    }

    /**
     * @param  array<int, string>|string  $entrypoints
     */
    public function vite(array|string $entrypoints, ?string $theme = null): string
    {
        return $this->assetResolver->vite($entrypoints, $theme);
    }

    public function mix(string $path, ?string $theme = null): string
    {
        return $this->assetResolver->mix($path, $theme);
    }

    protected function dispatch(object $event): void
    {
        $dispatcher = $this->events;

        if (! $dispatcher && function_exists('app') && app()->bound('events')) {
            $dispatcher = app('events');
        }

        $dispatcher?->dispatch($event);
    }
}
