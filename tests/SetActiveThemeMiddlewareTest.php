<?php

namespace Rawbinn\Themes\Tests;

use Illuminate\Http\Request;
use Rawbinn\Themes\Http\Middleware\SetActiveTheme;
use Rawbinn\Themes\Manifest\ThemeManifest;
use Rawbinn\Themes\Support\ThemeRegistry;
use Rawbinn\Themes\Themes;

class SetActiveThemeMiddlewareTest extends TestCase
{
    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = sys_get_temp_dir().'/rawbinn-themes-mw-'.uniqid();
        mkdir($this->themesPath.'/demo/views', 0777, true);

        file_put_contents($this->themesPath.'/demo/theme.json', json_encode([
            'slug' => 'demo',
            'name' => 'Demo Theme',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->themesPath.'/demo/views/home.blade.php', '<p>demo home</p>');

        config([
            'themes.paths.absolute' => $this->themesPath,
            'themes.paths.base' => 'themes',
            'themes.paths.assets' => 'assets',
            'themes.active' => 'demo',
            'themes.active_resolver' => null,
            'themes.cache_manifest' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->themesPath);
        parent::tearDown();
    }

    public function test_middleware_boots_active_theme_for_request(): void
    {
        $registry = new ThemeRegistry(app('files'), app('config'));
        $registry->setPath($this->themesPath);

        $manifest = new ThemeManifest(app('files'), app('config'), $registry);
        $themes = new Themes($registry, $manifest, app('files'), app('config'), app('view'));
        $themes->register();

        $middleware = new SetActiveTheme($themes);

        $response = $middleware->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('demo', $themes->getExplicitActive());
        $this->assertStringContainsString('demo home', $themes->view('home')->render());
    }
}
