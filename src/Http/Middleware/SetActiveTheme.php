<?php

namespace Rawbinn\Themes\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rawbinn\Themes\Themes;
use Symfony\Component\HttpFoundation\Response;

class SetActiveTheme
{
    public function __construct(
        protected Themes $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $active = $this->themes->getActive();

        if (is_string($active) && $active !== '' && $this->themes->exists($active)) {
            if ($this->themes->getExplicitActive() !== $active) {
                $this->themes->setActive($active);
            } else {
                $this->themes->bootTheme($active);
            }
        }

        return $next($request);
    }
}
