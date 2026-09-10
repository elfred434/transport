<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Kkiapay - vérification serveur d'une transaction
 * Basé sur le SDK officiel kkiapay/php-sdk mais sans dépendance Guzzle
 * Endpoint: POST {BASE_URL}/api/v1/transactions/status
 * Headers: X-API-KEY (public), X-PRIVATE-KEY, X-SECRET-KEY
 */
class KkiapayService
{
    private string $publicKey;
    private string $privateKey;
    private string $secret;
    private bool $sandbox;
    private string $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('kkiapay.public_key') ?: env('KKIAPAY_PUBLIC_KEY', '');
        $this->privateKey = config('kkiapay.private_key') ?: env('KKIAPAY_PRIVATE_KEY', '');
        $this->secret = config('kkiapay.secret') ?: env('KKIAPAY_SECRET', '');
        $this->sandbox = (bool) (config('kkiapay.sandbox') ?: env('KKIAPAY_SANDBOX', true));
        $this->baseUrl = $this->sandbox
            ? (config('kkiapay.sandbox_url') ?: 'https://api-sandbox.kkiapay.me')
            : (config('kkiapay.base_url') ?: 'https://api.kkiapay.me');
    }

    public function isConfigured(): bool
    {
        return $this->publicKey !== '' && $this->privateKey !== '' && $this->secret !== '';
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Vérifie une transaction Kkiapay par son transactionId
     * @return array{status:string, transactionId:string, amount:int, fees:int, source:string, ...}|null
     */
    public function verifyTransaction(string $transactionId): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Kkiapay non configuré');
            return null;
        }

        if ($transactionId === '') return null;

        try {
            $url = rtrim($this->baseUrl, '/') . '/api/v1/transactions/status';
            $response = Http::timeout(15)->withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => $this->publicKey,
                'X-PRIVATE-KEY' => $this->privateKey,
                'X-SECRET-KEY' => $this->secret,
            ])->post($url, [
                'transactionId' => $transactionId,
            ]);

            if (!$response->successful()) {
                Log::warning('Kkiapay verify échoué HTTP', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'tx' => $transactionId,
                ]);
                return null;
            }

            $data = $response->json();
            // SDK retourne objet direct, pas enveloppé
            if (is_array($data)) return $data;
            if (is_object($data)) return (array) $data;
            return null;
        } catch (\Throwable $e) {
            Log::error('Kkiapay verify exception: ' . $e->getMessage(), ['tx' => $transactionId]);
            return null;
        }
    }

    /**
     * Remboursement (optionnel)
     */
    public function refundTransaction(string $transactionId): ?array
    {
        if (!$this->isConfigured()) return null;
        try {
            $url = rtrim($this->baseUrl, '/') . '/api/v1/transactions/revert';
            $response = Http::timeout(15)->withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => $this->publicKey,
            ])->post($url, [
                'transactionId' => $transactionId,
            ]);
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Kkiapay refund exception: ' . $e->getMessage());
            return null;
        }
    }
}
