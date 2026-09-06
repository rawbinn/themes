<?php

namespace Rawbinn\Themes\Events;

class ThemeRegistered
{
    public function __construct(
        public readonly string $theme,
    ) {}
}
