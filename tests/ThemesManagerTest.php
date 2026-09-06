<?php

namespace Rawbinn\Themes\Tests;

use Illuminate\Support\Facades\Event;
use Rawbinn\Themes\Events\ThemeActivated;
use Rawbinn\Themes\Events\ThemeBooted;
use Rawbinn\Themes\Exceptions\ThemeNotFoundException;
use Rawbinn\Themes\Facades\Theme;
use Rawbinn\Themes\Manifest\ThemeManifest;
use Rawbinn\Themes\Support\ThemeRegistry;
use Rawbinn\Themes\Themes;

class ThemesManagerTest extends TestCase
{
    private string $themesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = sys_get_temp_dir().'/rawbinn-themes-'.uniqid();
        $this->seedThemes();
        $this->configureThemes();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->themesPath);
        parent::tearDown();
    }

    public function test_it_lists_installed_themes(): void
    {
        $this->assertEqualsCanonicalizing(['child', 'parent'], Theme::all());
    }

    public function test_it_validates_theme_structure(): void
    {
        $this->assertTrue(Theme::isValid('parent'));
        $this->assertTrue(Theme::isValid('child'));

        mkdir($this->themesPath.'/broken', 0777, true);
        Theme::setPath($this->themesPath);

        $this->assertFalse(Theme::isValid('broken'));
    }

    public function test_it_reads_manifest_safely(): void
    {
        $this->assertSame('Parent Theme', Theme::manifest('parent')['name']);
        $this->assertSame('parent', Theme::manifest('child')['parent']);
        $this->assertSame([], Theme::manifest('missing'));
    }

    public function test_it_resolves_child_views_with_parent_fallback(): void
    {
        Theme::setActive('child');

        $this->assertTrue(Theme::viewExists('index'));
        $this->assertTrue(Theme::viewExists('page'));
        $this->assertSame('parent::page', Theme::getView('page'));
        $this->assertStringContainsString('child index', Theme::view('index')->render());
        $this->assertStringContainsString('parent page', Theme::view('page')->render());
    }

    public function test_it_throws_when_setting_unknown_theme(): void
    {
        $this->expectException(ThemeNotFoundException::class);

        Theme::setActive('does-not-exist');
    }

    public function test_it_dispatches_theme_events(): void
    {
        Event::fake([ThemeActivated::class, ThemeBooted::class]);

        $manager = $this->makeThemesManager();
        $manager->setActive('parent');

        Event::assertDispatched(ThemeActivated::class, function (ThemeActivated $event) {
            return $event->theme === 'parent';
        });

        Event::assertDispatched(ThemeBooted::class, function (ThemeBooted $event) {
            return $event->theme === 'parent';
        });
    }

    public function test_it_boots_theme_functions_file(): void
    {
        file_put_contents($this->themesPath.'/parent/functions.php', '<?php $GLOBALS["theme_booted"] = true;');
        Theme::setPath($this->themesPath);
        Theme::registerNamespace('parent');
        Theme::setActive('parent');

        $this->assertTrue($GLOBALS['theme_booted'] ?? false);
    }

    public function test_it_generates_asset_urls(): void
    {
        Theme::setActive('parent');

        $this->assertStringContainsString('themes/parent/assets/css/app.css', Theme::asset('css/app.css'));
    }

    public function test_it_falls_back_to_parent_theme_assets(): void
    {
        mkdir($this->themesPath.'/parent/assets/css', 0777, true);
        file_put_contents($this->themesPath.'/parent/assets/css/shared.css', 'body {}');

        Theme::setPath($this->themesPath);
        Theme::flushState();
        Theme::setActive('child');

        $this->assertStringContainsString('themes/parent/assets/css/shared.css', Theme::asset('css/shared.css'));
    }

    public function test_it_resolves_mix_manifest_from_parent_theme(): void
    {
        file_put_contents($this->themesPath.'/parent/mix-manifest.json', json_encode([
            'js/app.js' => 'js/app.js',
        ]));
        mkdir($this->themesPath.'/parent/assets/js', 0777, true);
        file_put_contents($this->themesPath.'/parent/assets/js/app.js', 'console.log("app");');

        $manager = $this->makeThemesManager();
        $manager->setActive('child');

        $this->assertStringContainsString('themes/parent/assets/js/app.js', $manager->mix('js/app.js'));
    }

    public function test_it_caches_manifests_in_persistent_store(): void
    {
        config([
            'themes.manifest_cache_store' => 'array',
            'themes.manifest_cache_ttl' => 60,
        ]);

        $manager = $this->makeThemesManager();
        $first = $manager->manifest('parent');

        unlink($this->themesPath.'/parent/theme.json');

        $second = $manager->manifest('parent');

        $this->assertSame($first, $second);
        $this->assertSame('Parent Theme', $second['name']);
    }

    private function configureThemes(): void
    {
        config([
            'themes.paths.absolute' => $this->themesPath,
            'themes.paths.base' => 'themes',
            'themes.paths.assets' => 'assets',
            'themes.active' => null,
            'themes.active_resolver' => null,
            'themes.cache_manifest' => false,
        ]);

        Theme::setPath($this->themesPath);
        Theme::flushState();
        Theme::register();
    }

    private function makeThemesManager(): Themes
    {
        $registry = new ThemeRegistry(app('files'), app('config'));
        $registry->setPath($this->themesPath);

        $store = config('themes.manifest_cache_store');
        $cache = $store ? app('cache')->store($store) : null;

        $manifest = new ThemeManifest(app('files'), app('config'), $registry, $cache);

        $manager = new Themes(
            $registry,
            $manifest,
            app('files'),
            app('config'),
            app('view'),
            app('events'),
        );

        $manager->register();

        return $manager;
    }

    private function seedThemes(): void
    {
        mkdir($this->themesPath.'/parent/views', 0777, true);
        mkdir($this->themesPath.'/child/views', 0777, true);

        file_put_contents($this->themesPath.'/parent/theme.json', json_encode([
            'slug' => 'parent',
            'name' => 'Parent Theme',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->themesPath.'/parent/views/page.blade.php', '<p>parent page</p>');

        file_put_contents($this->themesPath.'/child/theme.json', json_encode([
            'slug' => 'child',
            'name' => 'Child Theme',
            'version' => '1.0.0',
            'parent' => 'parent',
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->themesPath.'/child/views/index.blade.php', '<p>child index</p>');
    }
}
