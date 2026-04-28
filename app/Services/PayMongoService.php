<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PayMongoService
{
    public function createGCashSource(
        int $amountInCentavos,
        string $currency = 'PHP',
        string $description = '',
        string $successUrl = '',
        string $failedUrl = '',
    ): array {
        return $this->createSource('gcash', $amountInCentavos, $currency, $description, $successUrl, $failedUrl);
    }

    public function createMayaSource(
        int $amountInCentavos,
        string $currency = 'PHP',
        string $description = '',
        string $successUrl = '',
        string $failedUrl = '',
    ): array {
        return $this->createSource('paymaya', $amountInCentavos, $currency, $description, $successUrl, $failedUrl);
    }

    public function retrieveSource(string $sourceId): array
    {
        return $this->sendRequest('GET', '/v1/sources/'.$sourceId);
    }

    public function createPaymentFromSource(string $sourceId, int $amountInCentavos, string $description): array
    {
        return $this->sendRequest('POST', '/v1/payments', [
            'data' => [
                'attributes' => [
                    'amount' => $amountInCentavos,
                    'currency' => 'PHP',
                    'description' => $description,
                    'source' => [
                        'id' => $sourceId,
                        'type' => 'source',
                    ],
                ],
            ],
        ]);
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        $secret = (string) config('services.paymongo.webhook_secret');

        if ($secret === '' || ! $this->isValidWebhookSignature($payload, $signature, $secret)) {
            throw new RuntimeException('Invalid PayMongo webhook signature.');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid PayMongo webhook payload.');
        }

        return $decoded;
    }

    private function isValidWebhookSignature(string $payload, string $header, string $secret): bool
    {
        $trimmedHeader = trim($header);

        if ($trimmedHeader === '') {
            return false;
        }

        $signatureParts = $this->extractWebhookSignatureParts($trimmedHeader);
        $timestamp = $signatureParts['t'] ?? null;

        if (! is_string($timestamp) || blank($timestamp)) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach (['li', 'te'] as $signatureKey) {
            $receivedSignature = $signatureParts[$signatureKey] ?? null;

            if (filled($receivedSignature) && hash_equals($expectedSignature, $receivedSignature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function extractWebhookSignatureParts(string $header): array
    {
        $parts = array_map('trim', explode(',', $header));
        $signatureParts = [];

        foreach ($parts as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $signatureParts[$key] = $value;
        }

        if ($signatureParts === []) {
            $signatureParts['raw'] = $header;
        }

        return $signatureParts;
    }

    private function createSource(
        string $type,
        int $amountInCentavos,
        string $currency,
        string $description,
        string $successUrl,
        string $failedUrl,
    ): array {
        return $this->sendRequest('POST', '/v1/sources', [
            'data' => [
                'attributes' => [
                    'amount' => $amountInCentavos,
                    'currency' => $currency,
                    'type' => $type,
                    'description' => $description,
                    'redirect' => [
                        'success' => $successUrl,
                        'failed' => $failedUrl,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $uri, array $payload = []): array
    {
        try {
            $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(15)
                ->retry([200, 500], throw: false)
                ->send($method, rtrim((string) config('services.paymongo.base_url'), '/').$uri, [
                    'json' => $payload,
                ]);
        } catch (Throwable $exception) {
            Log::error('PayMongo request threw an exception.', [
                'method' => $method,
                'uri' => $uri,
                'payload' => $payload,
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException('PayMongo request failed.', previous: $exception);
        }

        return $this->decodeResponse($response, $method, $uri, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response, string $method, string $uri, array $payload): array
    {
        if ($response->failed()) {
            Log::error('PayMongo request failed.', [
                'method' => $method,
                'uri' => $uri,
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('PayMongo request failed.');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = $response->json();

        if (! is_array($decoded)) {
            Log::error('PayMongo response could not be decoded.', [
                'method' => $method,
                'uri' => $uri,
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('PayMongo response could not be decoded.');
        }

        return $decoded;
    }
}
