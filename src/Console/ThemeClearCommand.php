<?php

namespace Rawbinn\Themes\Console;

use Illuminate\Console\Command;
use Rawbinn\Themes\Facades\Theme;

class ThemeClearCommand extends Command
{
    protected $signature = 'theme:clear';

    protected $description = 'Clear cached theme.json manifest files';

    public function handle(): int
    {
        Theme::clearManifestCache();

        $this->info('Theme manifest cache cleared.');

        return self::SUCCESS;
    }
}
