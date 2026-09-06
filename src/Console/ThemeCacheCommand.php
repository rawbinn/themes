<?php

namespace Rawbinn\Themes\Console;

use Illuminate\Console\Command;
use Rawbinn\Themes\Facades\Theme;

class ThemeCacheCommand extends Command
{
    protected $signature = 'theme:cache';

    protected $description = 'Cache all theme.json manifest files';

    public function handle(): int
    {
        if (! config('themes.manifest_cache_store')) {
            $this->warn('Persistent manifest caching is disabled. Set themes.manifest_cache_store in config.');
        }

        $count = Theme::warmManifestCache();

        $this->info("Cached {$count} theme manifest(s).");

        return self::SUCCESS;
    }
}
