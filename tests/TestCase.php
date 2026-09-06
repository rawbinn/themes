<?php

namespace Rawbinn\Themes\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Rawbinn\Themes\ThemesServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ThemesServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Theme' => \Rawbinn\Themes\Facades\Theme::class,
        ];
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
