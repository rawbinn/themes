<?php

namespace Rawbinn\Themes\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Rawbinn\Themes\Exceptions\InvalidThemeManifestException;
use Rawbinn\Themes\Facades\Theme;

class ThemeMakeCommand extends Command
{
    protected $signature = 'theme:make
                            {name : The display name of the theme}
                            {--slug= : Theme directory slug}
                            {--author= : Theme author}
                            {--parent= : Parent theme slug for child themes}';

    protected $description = 'Create a new theme scaffold';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $slug = (string) ($this->option('slug') ?: Str::slug($name));

        if ($slug === '') {
            $this->error('Theme slug cannot be empty.');

            return self::FAILURE;
        }

        if (Theme::exists($slug)) {
            $this->error("Theme [{$slug}] already exists.");

            return self::FAILURE;
        }

        $parent = $this->option('parent');

        if (is_string($parent) && $parent !== '' && ! Theme::exists($parent)) {
            $this->error("Parent theme [{$parent}] does not exist.");

            return self::FAILURE;
        }

        $themePath = Theme::getThemePath($slug);
        $stubPath = dirname(__DIR__, 2).'/stubs/theme';

        $this->createDirectory($themePath.'views/layouts');
        $this->createDirectory($themePath.'assets/css');
        $this->createDirectory($themePath.'assets/js');

        $replacements = [
            '{{ slug }}' => $slug,
            '{{ name }}' => $name,
            '{{ author }}' => (string) ($this->option('author') ?: 'Your Name'),
            '{{ version }}' => '1.0.0',
            '{{ parent }}' => is_string($parent) && $parent !== '' ? json_encode($parent) : 'null',
        ];

        $this->copyStub('theme.json.stub', $themePath.'theme.json', $replacements);
        $this->copyStub('functions.php.stub', $themePath.'functions.php', $replacements);
        $this->copyStub('index.blade.php.stub', $themePath.'views/index.blade.php', $replacements);
        $this->copyStub('app.blade.php.stub', $themePath.'views/layouts/app.blade.php', $replacements);
        $this->copyStub('app.css.stub', $themePath.'assets/css/app.css', $replacements);

        Theme::registerNamespace($slug);

        $this->info("Theme [{$slug}] created successfully.");
        $this->line("Path: {$themePath}");
        $this->line('Set THEME_ACTIVE='.$slug.' in your .env or config/themes.php active key.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function copyStub(string $stub, string $destination, array $replacements): void
    {
        $stubPath = dirname(__DIR__, 2).'/stubs/theme/'.$stub;

        if (! is_file($stubPath)) {
            throw new InvalidThemeManifestException("Stub file [{$stub}] is missing.");
        }

        $contents = str_replace(
            array_keys($replacements),
            array_values($replacements),
            file_get_contents($stubPath)
        );

        file_put_contents($destination, $contents);
    }

    protected function createDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
