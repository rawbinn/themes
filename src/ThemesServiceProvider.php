<?php

namespace Rawbinn\Themes;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Rawbinn\Themes\Console\ThemeCacheCommand;
use Rawbinn\Themes\Console\ThemeClearCommand;
use Rawbinn\Themes\Console\ThemeListCommand;
use Rawbinn\Themes\Console\ThemeMakeCommand;
use Rawbinn\Themes\Http\Middleware\SetActiveTheme;
use Rawbinn\Themes\Manifest\ThemeManifest;
use Rawbinn\Themes\Support\ThemeRegistry;

class ThemesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/themes.php' => config_path('themes.php'),
        ], 'themes-config');

        $this->registerMiddlewareAlias();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ThemeListCommand::class,
                ThemeMakeCommand::class,
                ThemeCacheCommand::class,
                ThemeClearCommand::class,
            ]);
        }

        $this->app->booted(function () {
            $themes = $this->app->make('themes');

            $active = $themes->getActive();

            if (is_string($active) && $active !== '' && $themes->exists($active)) {
                if ($themes->getExplicitActive() !== $active) {
                    $themes->setActive($active);
                } else {
                    $themes->bootTheme($active);
                }
            }
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/themes.php',
            'themes'
        );

        $this->registerServices();
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return ['themes', ThemeRegistry::class, ThemeManifest::class];
    }

    protected function registerServices(): void
    {
        $this->app->singleton(ThemeRegistry::class, function ($app) {
            return new ThemeRegistry($app['files'], $app['config']);
        });

        $this->app->singleton(ThemeManifest::class, function ($app) {
            $store = $app['config']->get('themes.manifest_cache_store');
            $cache = $store ? $app['cache']->store($store) : null;

            return new ThemeManifest(
                $app['files'],
                $app['config'],
                $app->make(ThemeRegistry::class),
                $cache,
            );
        });

        $this->app->singleton('themes', function ($app) {
            return new Themes(
                $app->make(ThemeRegistry::class),
                $app->make(ThemeManifest::class),
                $app['files'],
                $app['config'],
                $app['view'],
                $app->bound('events') ? $app['events'] : null,
            );
        });

        $this->app->booting(function ($app) {
            $app['themes']->register();
        });
    }

    protected function registerMiddlewareAlias(): void
    {
        if (! $this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];
        $alias = (string) $this->app['config']->get('themes.middleware_alias', 'theme');

        $router->aliasMiddleware($alias, SetActiveTheme::class);
    }
}
