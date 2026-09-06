<?php

namespace Rawbinn\Themes\Events;

class ThemeActivated
{
    public function __construct(
        public readonly string $theme,
        public readonly ?string $previous = null,
    ) {}
}
