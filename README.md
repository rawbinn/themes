# Rawbinn Themes

A lightweight theming package for Laravel 5 that lets you manage multiple themes with views, assets, and configuration.

## Requirements

- PHP >= 5.6.4
- Laravel 5.3

## Installation

Install via Composer:

```bash
composer require rawbinn/themes
```

Register the service provider in `config/app.php`:

```php
'providers' => [
    Rawbinn\Themes\ThemesServiceProvider::class,
],
```

Register the facade:

```php
'aliases' => [
    'Theme' => Rawbinn\Themes\Facades\Theme::class,
],
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="Rawbinn\Themes\ThemesServiceProvider"
```

## Configuration

The published config file `config/themes.php` contains:

```php
return [
    'active' => 'bootstrap',

    'paths' => [
        'absolute' => public_path('themes'),
        'base'     => 'themes',
        'assets'   => 'assets',
    ],
];
```

- **active** - Default theme to use if none is set at runtime.
- **paths.absolute** - Absolute filesystem path where themes are stored.
- **paths.base** - URL base path for generating asset URLs.
- **paths.assets** - Subdirectory within each theme for assets.

## Theme Structure

Each theme lives in its own directory under the themes path:

```
public/themes/
├── bootstrap/
│   ├── views/
│   ├── assets/
│   └── theme.json
└── default/
    ├── views/
    ├── assets/
    └── theme.json
```

- `theme.json` - Manifest file containing theme properties (e.g. `"parent": "default"`).
- `views/` - Blade view files for the theme.
- `assets/` - CSS, JS, images and other public assets.

## Usage

### Using the Facade

```php
use Theme;

// Get all themes
$themes = Theme::all();

// Check if a theme exists
Theme::exists('bootstrap');

// Set the active theme
Theme::setActive('default');

// Get the active theme
$active = Theme::getActive();
```

### Rendering Views

```php
// Render a theme view
return Theme::view('home', ['title' => 'Welcome']);

// Return a themed response
return Theme::response('home', ['title' => 'Welcome'], 200);
```

### Theme Layouts

```php
// Set a layout for the theme
Theme::setLayout('master');

// Get the current layout
$layout = Theme::getLayout();
```

### Assets

```php
// Generate an asset URL for the active theme
$url = Theme::asset('css/style.css');

// Generate a secure asset URL
$url = Theme::secureAsset('js/app.js');

// Generate an asset URL for a specific theme
$url = Theme::asset('bootstrap::css/style.css');
```

### Theme Properties

```php
// Read a property from theme.json
$parent = Theme::getProperty('bootstrap::parent');

// Set a property in theme.json
Theme::setProperty('bootstrap::title', 'My Theme');
```

### Functions JSON

Themes can include a `functions.json` manifest for custom configuration:

```php
$contents = Theme::getFunctionJsonContents('bootstrap');
$value = Theme::getFunctionProperty('bootstrap', 'key', 'default');
```

### Checking View Existence

```php
if (Theme::viewExists('home')) {
    return Theme::view('home');
}
```

## License

MIT License.
