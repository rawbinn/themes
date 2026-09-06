<?php

namespace Rawbinn\Themes\Events;

class ThemeBooted
{
    public function __construct(
        public readonly string $theme,
    ) {}
}
