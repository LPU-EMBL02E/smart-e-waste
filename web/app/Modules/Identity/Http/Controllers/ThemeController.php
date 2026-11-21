<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdateThemeRequest;
use App\Modules\Identity\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * POST /theme. API_Design.md §7.2, §7.6 ("Choose a theme"), P52.
 *
 * The choice belongs to the browser, not the account, so it is a cookie and
 * this route is open to everyone. Following the device needs no cookie at all.
 */
class ThemeController
{
    public function __invoke(UpdateThemeRequest $request): RedirectResponse
    {
        $theme = $request->enum('theme', Theme::class);

        $cookie = $theme === Theme::System
            ? Cookie::forget(Theme::COOKIE)
            : Cookie::make(Theme::COOKIE, $theme->value, config('ewaste.theme_cookie_days') * 24 * 60);

        return redirect()->back()->withCookie($cookie);
    }
}
