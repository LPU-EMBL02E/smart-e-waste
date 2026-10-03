<?php

namespace App\Support\Device;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown anywhere under /api/v1/device to answer with the error shape from
 * API_Design.md §3.5:
 *
 *   { "error": { "code": "BIN_FULL", "message": "...", "retryable": false } }
 *
 * Every device route answers in JSON, never with a redirect or an HTML page
 * (API_Design.md P2), so this is the single place that shape is produced.
 */
class DeviceApiException extends RuntimeException
{
    public function __construct(
        public readonly DeviceErrorCode $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode->value);
    }

    public static function of(DeviceErrorCode $code, string $message = ''): self
    {
        return new self($code, $message);
    }

    /**
     * Render as the device error shape. Laravel calls this automatically when an
     * exception exposes a render() method.
     */
    public function render(): JsonResponse
    {
        $response = new JsonResponse([
            'error' => [
                'code' => $this->errorCode->value,
                'message' => $this->getMessage(),
                'retryable' => $this->errorCode->isRetryable(),
            ],
        ], $this->errorCode->httpStatus());

        // DB_UNAVAILABLE carries a Retry-After. API_Design.md P7.
        if ($this->errorCode === DeviceErrorCode::DbUnavailable) {
            $response->header('Retry-After', (string) config('ewaste.device.retry_after_seconds'));
        }

        return $response;
    }
}
