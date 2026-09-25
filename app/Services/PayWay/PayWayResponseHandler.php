<?php

namespace Modules\Payment\Services\PayWay;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Services\PayWay\DTOs\PayWayResponse;

final class PayWayResponseHandler
{
    /**
     * Handle PayWay API response.
     */
    public function handle(
        Response $response,
        string $context,
        array $successCodes = ['00']
    ): PayWayResponse {
        $body = $response->json() ?? $response->body();

        $this->logResponse($response, $body, $context);

        if (!$response->successful() || !is_array($body)) {
            return PayWayResponse::error(
                $this->extractErrorMessage($body),
                is_array($body) ? $body : ['raw_response' => $body]
            );
        }

        $statusCode = $body['status']['code'] ?? '';
        $isSuccess = in_array($statusCode, $successCodes, false);

        if ($isSuccess) {
            return PayWayResponse::success($body);
        }

        return PayWayResponse::error(
            $body['status']['message'] ?? 'Unknown error',
            $body
        );
    }

    /**
     * Log the response for debugging.
     */
    private function logResponse(Response $response, mixed $body, string $context): void
    {
        $logBody = $body;

        if (is_array($logBody)) {
            // Remove large data like QR images from logs
            $logBody = array_diff_key($logBody, array_flip(['qrImage', 'qr_image']));
        } elseif (is_string($logBody)) {
            $logBody = substr($logBody, 0, 500);
        }

        Log::info("PayWay: {$context} response", [
            'status' => $response->status(),
            'body' => $logBody,
        ]);
    }

    /**
     * Extract error message from response body.
     */
    private function extractErrorMessage(mixed $body): string
    {
        if (is_array($body)) {
            return $body['status']['message'] ?? 'Unknown error';
        }

        return 'Invalid response';
    }
}
