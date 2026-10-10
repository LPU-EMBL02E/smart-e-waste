<?php

use App\Modules\Devices\Http\Middleware\VerifyDeviceSignature;
use App\Modules\Identity\Http\Middleware\EnsureUserIsAdmin;
use App\Support\Device\DeviceExceptions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'device.signed' => VerifyDeviceSignature::class,
        ]);

        // Trust the local Nginx hop and read the standard X-Forwarded-* headers,
        // never Cloudflare's own CF-Connecting-IP. This keeps login rate
        // limiting and audit logs correct under all three ingress setups
        // (System_Plan.md §7.1).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every failure under /api/v1/device answers in the device error shape,
        // never HTML and never a redirect (API_Design.md §3.5, P2).
        DeviceExceptions::register($exceptions);
    })->create();
