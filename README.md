# Rawbinn Themes

A standalone theme engine for Laravel. Use Blade namespaces, parent themes, manifests, and public assets without coupling to a CMS.

## Requirements

- PHP 8.3+
- Laravel 13+

## Installation

```bash
composer require rawbinn/themes
php artisan vendor:publish --tag=themes-config
```

Laravel auto-registers the service provider and `Theme` facade.

## Quick start

### 1. Create a theme

```
public/themes/mytheme/
├── theme.json
├── functions.php
├── assets/
│   └── css/app.css
└── views/
    ├── layouts/
    │   └── app.blade.php
    └── index.blade.php
```

**theme.json**

```json
{
    "slug": "mytheme",
    "name": "My Theme",
    "version": "1.0.0",
    "parent": null
}
```

**functions.php** (optional)

```php
<?php

// Register view composers, bindings, etc.
```

### 2. Configure

In `.env`:

```env
THEME_ACTIVE=mytheme
```

Or in `config/themes.php`:

```php
'active' => 'mytheme',
```

### 3. Render views

```php
use Rawbinn\Themes\Facades\Theme;

Route::get('/', fn () => Theme::view('index', ['title' => 'Home']));
```

In Blade:

```blade
<link href="{{ Theme::asset('css/app.css') }}" rel="stylesheet">
```

## Configuration

| Key | Description |
|-----|-------------|
| `active` | Default theme slug (`THEME_ACTIVE` env) |
| `active_resolver` | Callable returning the active theme at runtime |
| `boot_file` | Bootstrap file loaded from theme root (default: `functions.php`) |
| `cache_manifest` | Cache parsed `theme.json` per request |
| `paths.absolute` | Theme directory (default: `public/themes`) |
| `paths.base` | URL segment for assets |
| `paths.assets` | Assets subdirectory inside each theme |

## Runtime active theme resolver

For CMS or multi-tenant apps:

```php
// AppServiceProvider::boot()
config([
    'themes.active_resolver' => fn () => tenant()->theme ?? config('themes.active'),
]);
```

## Parent themes

Set `parent` in `theme.json`:

```json
{
    "slug": "child",
    "name": "Child Theme",
    "parent": "parent"
}
```

Missing views fall back to the parent theme namespace.

## Plugin packages (optional)

Declare plugins to activate when a theme is enabled:

```json
{
    "slug": "mytheme",
    "name": "My Theme",
    "packages": [
        { "slug": "contact" }
    ]
}
```

## Vite and Mix

### Vite (Laravel 13+)

Add to `theme.json`:

```json
"vite": {
    "build_directory": "build",
    "hot_file": "theme.hot"
}
```

In your layout:

```blade
{!! Theme::vite(['resources/css/app.css', 'resources/js/app.js']) !!}
```

Vite build output should live at `public/themes/{slug}/build/`.

### Laravel Mix

Place `mix-manifest.json` in the theme root. The resolver falls back to the parent theme when needed.

```blade
<script src="{{ Theme::mix('js/app.js') }}"></script>
```

## Manifest caching

```bash
# Warm persistent manifest cache
php artisan theme:cache

# Clear cached manifests
php artisan theme:clear
```

Configure in `.env`:

```env
THEME_MANIFEST_CACHE_STORE=file
THEME_MANIFEST_CACHE_TTL=3600
```

## Larapress bridge

Larapress registers `LarapressThemeBridge` to resolve the active theme from the `theme-active` setting. Theme options, menus, and CPTs remain Larapress-specific.

## Artisan commands

```bash
# List installed themes
php artisan theme:list

# Scaffold a new theme
php artisan theme:make "My Theme" --slug=mytheme --author="Your Name"

# Child theme
php artisan theme:make "Child Theme" --slug=child --parent=parent

# Cache manifests
php artisan theme:cache
php artisan theme:clear
```

## Middleware

Apply the `theme` middleware to routes that should resolve and boot the active theme:

```php
Route::middleware(['web', 'theme'])->group(function () {
    Route::get('/', fn () => Theme::view('index'));
});
```

The alias is configurable via `themes.middleware_alias` in config.

## Events

| Event | When |
|-------|------|
| `ThemeRegistered` | Blade namespace registered for a theme |
| `ThemeActivated` | Active theme changes via `setActive()` |
| `ThemeBooted` | Theme bootstrap file loaded |

```php
use Rawbinn\Themes\Events\ThemeActivated;

Event::listen(ThemeActivated::class, function (ThemeActivated $event) {
    // $event->theme, $event->previous
});
```

## API reference

| Method | Description |
|--------|-------------|
| `Theme::all()` | List theme directory names |
| `Theme::allValid()` | Themes with `theme.json` and `views/` |
| `Theme::exists($slug)` | Theme folder exists |
| `Theme::isValid($slug)` | Has manifest and views |
| `Theme::getActive()` | Current theme slug |
| `Theme::setActive($slug)` | Set and boot theme |
| `Theme::bootTheme($slug)` | Load `functions.php` |
| `Theme::manifest($slug)` | Read `theme.json` safely |
| `Theme::view($name, $data)` | Render with parent fallback |
| `Theme::viewExists($name)` | Check resolved view |
| `Theme::asset($path)` | Public asset URL |
| `Theme::vite($entries)` | Theme-aware Vite tags |
| `Theme::mix($path)` | Theme-aware Mix URL |
| `Theme::warmManifestCache()` | Cache all theme.json files |
| `Theme::clearManifestCache()` | Clear manifest cache |
| `Theme::getThemePath($slug)` | Absolute theme path |

## Testing

From the package directory:

```bash
composer install
composer test
```

From the monorepo root:

```bash
composer test:themes
```

## Larapress integration

[Larapress](https://github.com/rawbinn/larapress) uses this package for view/asset resolution and registers `themes.active_resolver` to read `theme-active` from the database. Larapress-specific features (theme options, menus, CPTs) remain in Larapress.

## License

MIT
