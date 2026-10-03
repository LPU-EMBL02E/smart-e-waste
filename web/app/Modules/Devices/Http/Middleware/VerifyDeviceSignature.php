<?php

namespace App\Modules\Devices\Http\Middleware;

use App\Modules\Devices\Models\Device;
use App\Support\Device\DeviceApiException;
use App\Support\Device\DeviceErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC-SHA256 authentication for /api/v1/device/*. API_Design.md §3.2–§3.3.
 *
 * Headers on every signed request:
 *
 *   X-Device-Code: BIN-01
 *   X-Timestamp:   1790956800
 *   X-Signature:   hex( HMAC-SHA256( secret,
 *                    timestamp + "\n" + METHOD + "\n" + path + "\n" + raw_body ) )
 *
 * The secret never crosses the wire, so the scheme is the same over plain HTTP
 * on the LAN and over HTTPS (System_Plan.md §5.2). GET /device/time is the one
 * unsigned route and does not pass through this middleware.
 *
 * This is the only class in the application that knows how a device
 * authenticates. Swapping the scheme is a change to this one file
 * (System_Plan.md §8.1).
 *
 * The resolved Device is attached to the request so controllers never look it up
 * again:
 *
 *   $request->attributes->get('device')
 *
 * STATUS: written from the spec but NOT yet executed — there is no PHP in the
 * authoring environment. Verify with tools/simulator/test_vectors.py and
 * tests/Feature/Device/DeviceSignatureTest.php before trusting it.
 */
class VerifyDeviceSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceCode = $request->header('X-Device-Code');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        // Step 1: all three headers present, and the device code exists. An
        // unknown device code gets the same answer as a bad signature, so a
        // caller cannot enumerate device codes.
        if (! is_string($deviceCode) || ! is_string($timestamp) || ! is_string($signature)) {
            throw DeviceApiException::of(DeviceErrorCode::InvalidSignature, 'Missing signature headers.');
        }

        $device = Device::where('device_code', $deviceCode)->first();

        if ($device === null) {
            throw DeviceApiException::of(DeviceErrorCode::InvalidSignature, 'Invalid signature.');
        }

        // Step 2: the recomputed signature matches, compared in constant time.
        $expected = $this->sign($device->secret, $timestamp, $request);

        if (! hash_equals($expected, strtolower($signature))) {
            throw DeviceApiException::of(DeviceErrorCode::InvalidSignature, 'Invalid signature.');
        }

        // Step 3: the timestamp is inside the signature window. The ESP32 has no
        // clock: it fetches GET /device/time at boot and after any CLOCK_SKEW
        // error, then counts forward from that.
        $window = (int) config('ewaste.device.signature_window_seconds');

        if (abs(time() - (int) $timestamp) > $window) {
            throw DeviceApiException::of(DeviceErrorCode::ClockSkew, 'Timestamp outside the signature window.');
        }

        // Step 4: the device is active. Checked AFTER the signature, so only a
        // caller holding the secret can learn that a device is inactive.
        if (! $device->isActive()) {
            throw DeviceApiException::of(DeviceErrorCode::DeviceInactive, 'This device is not active.');
        }

        // API_Design.md P6: last_seen_at is written once the request is accepted.
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('device', $device);

        return $next($request);
    }

    /**
     * The canonical string and its HMAC. API_Design.md §3.2, P4.
     *
     *   key       the bytes of the secret exactly as issued, not hex-decoded
     *   timestamp the same decimal string sent in X-Timestamp
     *   METHOD    upper case
     *   path      from /api/v1/device onward, no scheme, host or query string
     *   raw_body  the exact bytes received; empty for a request with no body,
     *             so the signed string then ends with "\n"
     *   output    lower-case hex, 64 characters
     *
     * Signs the RAW body, never re-encoded JSON: `json_encode(json_decode($b))`
     * would reorder keys and change whitespace, and the signature would not match.
     *
     * Checked against both vectors in API_Design.md §3.4.
     */
    private function sign(string $secret, string $timestamp, Request $request): string
    {
        $canonical = $timestamp."\n"
            .strtoupper($request->method())."\n"
            .'/'.$request->path()."\n"
            .$request->getContent();

        return hash_hmac('sha256', $canonical, $secret);
    }
}
