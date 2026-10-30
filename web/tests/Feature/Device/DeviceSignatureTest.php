<?php

namespace Tests\Feature\Device;

use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * The signing contract between the firmware and the server.
 * API_Design.md §3.2–§3.5, P5, P6, P8.
 *
 * The two vectors below are the SAME ones the firmware and
 * tools/simulator/test_vectors.py check. If all three agree, a signature
 * mismatch during integration is a key or clock problem, not a canonical-string
 * problem — which is the one bug that is miserable to find with real hardware.
 *
 * Both vector tests send the documented signature as a literal, never one
 * recomputed here, so the middleware is checked against the document itself.
 */
class DeviceSignatureTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const SECRET = 'test-secret-do-not-use-0123456789abcdef';

    private const TIMESTAMP = 1790956800;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::factory()->create(['secret' => self::SECRET]);
    }

    /** Vector 1: GET with no body. The signed string ends with "\n". */
    public function test_it_accepts_the_documented_get_vector(): void
    {
        $expected = '30f42c3aca93595dedf2c7a61ad832036cf3de02cb9f8dce7f478b8e049b680f';

        $signature = hash_hmac(
            'sha256',
            self::TIMESTAMP."\nGET\n/api/v1/device/config\n",
            self::SECRET
        );

        $this->assertSame($expected, $signature);

        $this->travelTo(Carbon::createFromTimestamp(self::TIMESTAMP));

        $this->sendWithSignature('GET', '/api/v1/device/config', '', $expected)->assertOk();
    }

    /** Vector 2: POST with a 41-byte body, signed as raw bytes. */
    public function test_it_accepts_the_documented_post_vector(): void
    {
        $expected = '8c7cd80ead0a593505a15b215d85476e1a897caf2e71baf11c1eeff699659e7f';
        $body = '{"qr_token":"TESTTOKEN0001","fw":"1.0.3"}';

        $this->assertSame(41, strlen($body));

        $signature = hash_hmac(
            'sha256',
            self::TIMESTAMP."\nPOST\n/api/v1/device/sessions\n".$body,
            self::SECRET
        );

        $this->assertSame($expected, $signature);

        $this->travelTo(Carbon::createFromTimestamp(self::TIMESTAMP));
        RewardRule::factory()->create();
        $student = User::factory()->create();
        $student->qrCredential->update(['token' => 'TESTTOKEN0001']);

        // Sent RAW with ->call(), so Laravel does not re-encode the JSON.
        $this->sendWithSignature('POST', '/api/v1/device/sessions', $body, $expected)->assertCreated();
    }

    public function test_it_rejects_a_bad_signature(): void
    {
        $this->sendWithSignature('GET', '/api/v1/device/config', '', str_repeat('0', 64))
            ->assertUnauthorized()
            ->assertExactJson($this->errorShape('INVALID_SIGNATURE', 'Invalid signature.', false));
    }

    public function test_it_gives_an_unknown_device_the_bad_signature_answer(): void
    {
        $stranger = Device::factory()->make(['device_code' => 'BIN-99']);

        $this->deviceGet($stranger, '/api/v1/device/config')
            ->assertUnauthorized()
            ->assertExactJson($this->errorShape('INVALID_SIGNATURE', 'Invalid signature.', false));
    }

    public function test_it_rejects_a_timestamp_outside_the_window_as_clock_skew(): void
    {
        $window = config('ewaste.device.signature_window_seconds');

        $this->deviceSend($this->device, 'GET', '/api/v1/device/config', timestamp: now()->getTimestamp() - $window)
            ->assertOk();

        $this->deviceSend($this->device, 'GET', '/api/v1/device/config', timestamp: now()->getTimestamp() - $window - 1)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'CLOCK_SKEW')
            ->assertJsonPath('error.retryable', true);
    }

    public function test_it_rejects_an_inactive_device_only_after_the_signature_passes(): void
    {
        $this->device->update(['status' => Device::STATUS_INACTIVE]);

        $this->sendWithSignature('GET', '/api/v1/device/config', '', str_repeat('0', 64))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_SIGNATURE');

        $this->deviceGet($this->device, '/api/v1/device/config')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DEVICE_INACTIVE');
    }

    public function test_it_answers_time_without_a_signature(): void
    {
        $this->freezeSecond();

        $this->getJson('/api/v1/device/time')
            ->assertOk()
            ->assertExactJson(['time' => now()->getTimestamp()]);
    }

    public function test_it_answers_a_malformed_body_with_a_validation_error(): void
    {
        $this->deviceSend($this->device, 'POST', '/api/v1/device/telemetry', '{"distance_mm": 412.5,')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.retryable', false);

        $this->assertDatabaseCount('bin_telemetry', 0);
    }

    public function test_it_sets_last_seen_at(): void
    {
        $this->freezeSecond();
        $this->assertNull($this->device->last_seen_at);

        $this->deviceGet($this->device, '/api/v1/device/config')->assertOk();

        $this->assertTrue($this->device->fresh()->last_seen_at->equalTo(now()));
    }

    /** Sends a request with a given X-Signature, as a bin would. */
    private function sendWithSignature(string $method, string $path, string $body, string $signature): TestResponse
    {
        return $this->call($method, $path, server: [
            'HTTP_X_DEVICE_CODE' => $this->device->device_code,
            'HTTP_X_TIMESTAMP' => (string) now()->getTimestamp(),
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);
    }

    private function errorShape(string $code, string $message, bool $retryable): array
    {
        return ['error' => ['code' => $code, 'message' => $message, 'retryable' => $retryable]];
    }
}
