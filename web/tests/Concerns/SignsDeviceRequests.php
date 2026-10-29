<?php

namespace Tests\Concerns;

use App\Modules\Devices\Models\Device;
use Illuminate\Testing\TestResponse;

/**
 * Sends signed device requests the way the firmware does.
 * API_Design.md §3.2.
 *
 * The body is encoded once and those exact bytes are both signed and sent, with
 * ->call() rather than ->postJson(), because re-encoded JSON could differ from
 * the signed bytes. This is the only signing code in the test suite.
 *
 * Paths are full paths, as they appear in the canonical string:
 * '/api/v1/device/config'.
 */
trait SignsDeviceRequests
{
    protected function deviceGet(Device $device, string $path): TestResponse
    {
        return $this->deviceSend($device, 'GET', $path);
    }

    protected function devicePost(Device $device, string $path, array $body = []): TestResponse
    {
        return $this->deviceSend($device, 'POST', $path, $body === [] ? '' : json_encode($body));
    }

    /**
     * Signs and sends raw bytes. The timestamp defaults to the server's now, so
     * a test that travels in time stays inside the signature window.
     */
    protected function deviceSend(
        Device $device,
        string $method,
        string $path,
        string $rawBody = '',
        ?int $timestamp = null,
    ): TestResponse {
        $timestamp = (string) ($timestamp ?? now()->getTimestamp());
        $signature = hash_hmac('sha256', "{$timestamp}\n{$method}\n{$path}\n{$rawBody}", $device->secret);

        return $this->call($method, $path, server: [
            'HTTP_X_DEVICE_CODE' => $device->device_code,
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $rawBody);
    }
}
