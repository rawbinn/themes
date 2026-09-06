<?php

namespace Rawbinn\Themes\Console;

use Illuminate\Console\Command;
use Rawbinn\Themes\Facades\Theme;

class ThemeListCommand extends Command
{
    protected $signature = 'theme:list';

    protected $description = 'List all installed themes';

    public function handle(): int
    {
        $themes = Theme::all();

        if ($themes === []) {
            $this->warn('No themes found in: '.Theme::getPath());

            return self::SUCCESS;
        }

        $active = Theme::getActive();
        $rows = [];

        foreach ($themes as $theme) {
            $manifest = Theme::manifest($theme);
            $rows[] = [
                $theme,
                $manifest['name'] ?? '—',
                $manifest['version'] ?? '—',
                Theme::isValid($theme) ? 'yes' : 'no',
                $theme === $active ? 'yes' : 'no',
            ];
        }

        $this->table(
            ['Slug', 'Name', 'Version', 'Valid', 'Active'],
            $rows
        );

        return self::SUCCESS;
    }
}
