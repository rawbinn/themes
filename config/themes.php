<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Active Theme
    |--------------------------------------------------------------------------
    */

    'active' => env('THEME_ACTIVE'),

    'active_resolver' => null,

    'boot_file' => 'functions.php',

    /*
    |--------------------------------------------------------------------------
    | Manifest Caching
    |--------------------------------------------------------------------------
    |
    | cache_manifest enables in-memory caching for the current request.
    | manifest_cache_store enables persistent cache (file, redis, etc.).
    |
    */

    'cache_manifest' => true,

    'manifest_cache_store' => env('THEME_MANIFEST_CACHE_STORE'),

    'manifest_cache_ttl' => (int) env('THEME_MANIFEST_CACHE_TTL', 3600),

    'manifest_cache_key' => 'rawbinn.themes.manifest',

    'middleware_alias' => 'theme',

    /*
    |--------------------------------------------------------------------------
    | Vite / Mix
    |--------------------------------------------------------------------------
    */

    'vite' => [
        'build_directory' => 'build',
        'hot_file' => 'theme.hot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [

        'absolute' => public_path('themes'),

        'base' => 'themes',

        'assets' => 'assets',

    ],

];
