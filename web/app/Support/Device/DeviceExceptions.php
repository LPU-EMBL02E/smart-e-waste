<?php

namespace App\Support\Device;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

/**
 * Makes every failure under /api/v1/device answer in the device error shape.
 * API_Design.md §3.5 and P2.
 *
 * Device routes must NEVER answer with a redirect, an HTML page, or Laravel's
 * default validation body. The firmware branches on `error.code`, and a reply
 * it cannot read is treated as no reply at all — which, after an item is
 * physically in the bin, means a retry loop instead of credited points (P9).
 *
 * DeviceApiException renders itself, so it is not listed here.
 *
 * Wired up in bootstrap/app.php:
 *
 *     ->withExceptions(function (Exceptions $exceptions) {
 *         DeviceExceptions::register($exceptions);
 *     })
 *
 * Keeping it in a class rather than inline in bootstrap/app.php means the
 * framework file needs one line, and this logic stays in the repository where
 * it can be reviewed and tested.
 */
final class DeviceExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        // A field broke its rule, or the body was not valid JSON. The session is
        // unchanged, so the firmware can correct and resend (§6).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! self::isDeviceRequest($request)) {
                return null;   // fall through to Laravel's normal handling
            }

            return self::respond(
                DeviceErrorCode::ValidationError,
                $e->validator->errors()->first() ?: 'The request body is not valid.'
            );
        });

        // The database could not be reached — including from the signature
        // middleware's device lookup. Retryable, with Retry-After (§6).
        $exceptions->render(function (QueryException|PDOException $e, Request $request) {
            if (! self::isDeviceRequest($request)) {
                return null;
            }

            return self::respond(DeviceErrorCode::DbUnavailable, 'The database is unavailable.');
        });

        // Last resort: anything else on a device route still has to be JSON in
        // the error shape rather than an HTML error page.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::isDeviceRequest($request) || $e instanceof DeviceApiException) {
                return null;
            }

            // A deliberate HTTP status (404 on an unknown route, 405 on the
            // wrong method) is left alone; only unexpected failures are mapped.
            if (method_exists($e, 'getStatusCode')) {
                return null;
            }

            return self::respond(DeviceErrorCode::DbUnavailable, 'The server could not complete the request.');
        });
    }

    private static function isDeviceRequest(Request $request): bool
    {
        return $request->is('api/v1/device/*');
    }

    private static function respond(DeviceErrorCode $code, string $message): JsonResponse
    {
        return (new DeviceApiException($code, $message))->render();
    }
}
