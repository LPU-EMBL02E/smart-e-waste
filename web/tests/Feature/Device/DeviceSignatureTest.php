<?php

namespace Tests\Feature\Device;

use Tests\TestCase;

/**
 * The signing contract between the firmware and the server.
 * API_Design.md §3.2–§3.4.
 *
 * The two vectors below are the SAME ones the firmware and
 * tools/simulator/test_vectors.py check. If all three agree, a signature
 * mismatch during integration is a key or clock problem, not a canonical-string
 * problem — which is the one bug that is miserable to find with real hardware.
 *
 * Both expected signatures were reproduced independently while this test was
 * written, so they are known good.
 *
 * TODO: all test bodies. Also cover, from API_Design.md §8:
 *   - a bad signature                 401 INVALID_SIGNATURE
 *   - an unknown device code          401 INVALID_SIGNATURE (same answer)
 *   - a timestamp outside the window  401 CLOCK_SKEW
 *   - an inactive device              403 DEVICE_INACTIVE, and only AFTER the
 *                                     signature check passes
 *   - GET /device/time unsigned       200
 *   - a malformed body                422 VALIDATION_ERROR in the error shape
 */
class DeviceSignatureTest extends TestCase
{
    private const SECRET = 'test-secret-do-not-use-0123456789abcdef';
    private const TIMESTAMP = 1790956800;

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

        // TODO: register a device with this secret and assert the request is
        // accepted by VerifyDeviceSignature.
        $this->markTestIncomplete('Device factory and route assertion still to write.');
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

        // TODO: send the RAW body with ->call() so Laravel does not re-encode
        // it. Re-encoded JSON would reorder keys and break the signature.
        $this->markTestIncomplete('Device factory and route assertion still to write.');
    }
}
