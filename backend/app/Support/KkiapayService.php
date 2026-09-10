<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Kkiapay - version production-ready
 *
 * Documentation officielle :
 *  - Paiements (collecte) : POST /api/v1/transactions/status
 *  - Remboursement : POST /api/v1/transactions/revert
 *  - Planification de payout automatique : POST /api/v1/payouts/setup  (roof/rate)
 *     algo=roof : verse quand le solde atteint un plafond (roof_amount)
 *     algo=rate : verse selon fréquence (rate_frequency : "1m"/"3j"/"1w")
 *  - Payout instantané (Merchant Pro UNIQUEMENT) : POST /api/v1/payouts
 *  - Solde : GET /api/v1/merchants/me ou /api/v1/balance
 *
 * IMPORTANT : En mode sandbox avec des clés valides, l'API renvoie des statuts réels.
 * Les fallbacks "SUCCESS simulé" sont désactivés par défaut (ils masquaient les erreurs).
 * Pour les réactiver en dev, mettre KKIAPAY_FALLBACK_SIMULATE=true.
 */
class KkiapayService
{
    private string $publicKey;
    private string $privateKey;
    private string $secret;
    private bool $sandbox;
    private bool $simulate;
    private string $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('kkiapay.public_key') ?: env('KKIAPAY_PUBLIC_KEY', '');
        $this->privateKey = config('kkiapay.private_key') ?: env('KKIAPAY_PRIVATE_KEY', '');
        $this->secret = config('kkiapay.secret') ?: env('KKIAPAY_SECRET', '');
        $this->sandbox = (bool) (config('kkiapay.sandbox') ?: env('KKIAPAY_SANDBOX', true));
        $this->simulate = (bool) env('KKIAPAY_FALLBACK_SIMULATE', false);
        $this->baseUrl = $this->sandbox
            ? rtrim(config('kkiapay.sandbox_url') ?: 'https://api-sandbox.kkiapay.me', '/')
            : rtrim(config('kkiapay.base_url') ?: 'https://api.kkiapay.me', '/');
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

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------
    private function clientOptions(): array
    {
        $opts = ['timeout' => 20];
        if (env('APP_ENV') === 'local' || env('KKIAPAY_SKIP_SSL_VERIFY', true)) {
            $opts['verify'] = false;
        }
        return $opts;
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-key' => $this->publicKey,
            'x-private-key' => $this->privateKey,
            'x-secret-key' => $this->secret,
        ];
    }

    private function simulateResponse(string $reason, array $extra = []): array
    {
        Log::warning("Kkiapay SIMULATION ({$reason})", $extra);
        return array_merge([
            'status' => 'SUCCESS',
            'simulated' => true,
            'simulation_reason' => $reason,
        ], $extra);
    }

    // -------------------------------------------------------------------------
    // Vérification d'un paiement entrant (client a payé via widget)
    // -------------------------------------------------------------------------
    public function verifyTransaction(string $transactionId): ?array
    {
        if ($transactionId === '') return null;

        if (!$this->isConfigured()) {
            Log::warning('Kkiapay non configuré - verifyTransaction');
            if ($this->simulate) {
                return $this->simulateResponse('no-config', ['transactionId' => $transactionId]);
            }
            return null;
        }

        $url = $this->baseUrl . '/api/v1/transactions/status';
        try {
            $resp = Http::withOptions($this->clientOptions())
                ->withHeaders($this->headers())
                ->post($url, ['transactionId' => $transactionId]);

            Log::info('Kkiapay verifyTransaction', [
                'tx' => $transactionId,
                'http' => $resp->status(),
                'body' => substr($resp->body(), 0, 800),
            ]);

            if ($resp->successful() && is_array($resp->json())) {
                return $resp->json();
            }

            // 404 = transaction inconnue = échec
            if ($resp->status() === 404) return null;

            // 401 = clés invalides
            if ($resp->status() === 401) {
                if ($this->simulate) {
                    return $this->simulateResponse('invalid-keys-verify', ['transactionId' => $transactionId]);
                }
                Log::error('Kkiapay clés invalides (401) lors de verifyTransaction');
                return null;
            }
        } catch (\Throwable $e) {
            Log::error('Kkiapay verifyTransaction exception: ' . $e->getMessage());
            if ($this->simulate) {
                return $this->simulateResponse('exception-verify', ['transactionId' => $transactionId, 'error' => $e->getMessage()]);
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Remboursement
    // -------------------------------------------------------------------------
    public function refundTransaction(string $transactionId): ?array
    {
        if (!$this->isConfigured()) return null;
        try {
            $resp = Http::withOptions($this->clientOptions())
                ->withHeaders($this->headers())
                ->post($this->baseUrl . '/api/v1/transactions/revert', ['transactionId' => $transactionId]);
            return $resp->json();
        } catch (\Throwable $e) {
            Log::error('Kkiapay refund exception: ' . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Planification de payout automatique (roof / rate)
    // Configuration DURABLE chez Kkiapay : appeler une seule fois (ou pour modifier)
    // -------------------------------------------------------------------------
    public function setupPayoutRoof(string $destinationPhone, float $roofAmount = 50000): array
    {
        $destination = $this->normalizePhone($destinationPhone);

        if (!$this->isConfigured()) {
            Log::warning('Kkiapay non configuré - setupPayoutRoof');
            return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré'];
        }

        // Min 50 000 FCFA selon la documentation Kkiapay
        if ($roofAmount < 50000) {
            Log::info("Kkiapay setupPayout: roof {$roofAmount} est sous le minimum 50000 FCFA, on force à 50000");
            $roofAmount = 50000;
        }

        $url = $this->baseUrl . '/api/v1/payouts/setup';
        $payload = [
            'algorithm' => 'roof',
            'send_notification' => true,
            'destination_type' => 'MOBILE_MONEY',
            'destination' => $destination,
            'roof_amount' => (string) $roofAmount,
        ];

        try {
            $resp = Http::withOptions($this->clientOptions())
                ->withHeaders($this->headers())
                ->post($url, $payload);

            Log::info('Kkiapay setupPayoutRoof', [
                'destination' => $destination,
                'roof' => $roofAmount,
                'http' => $resp->status(),
                'body' => substr($resp->body(), 0, 1000),
            ]);

            $json = $resp->json();
            if ($resp->successful() && is_array($json)) {
                return array_merge(['status' => 'SUCCESS', 'setup' => true, 'destination' => $destination, 'roof_amount' => $roofAmount], $json);
            }

            return [
                'status' => 'FAILED',
                'http_status' => $resp->status(),
                'body' => $resp->body(),
                'destination' => $destination,
                'roof_amount' => $roofAmount,
            ];
        } catch (\Throwable $e) {
            Log::error('Kkiapay setupPayoutRoof exception: ' . $e->getMessage());
            return ['status' => 'FAILED', 'error' => $e->getMessage()];
        }
    }

    public function setupPayoutRate(string $destinationPhone, string $frequency = '1w'): array
    {
        $destination = $this->normalizePhone($destinationPhone);
        if (!$this->isConfigured()) return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré'];
        if (!in_array($frequency, ['1m', '3j', '1w'], true)) $frequency = '1w';

        try {
            $resp = Http::withOptions($this->clientOptions())
                ->withHeaders($this->headers())
                ->post($this->baseUrl . '/api/v1/payouts/setup', [
                    'algorithm' => 'rate',
                    'send_notification' => true,
                    'destination_type' => 'MOBILE_MONEY',
                    'destination' => $destination,
                    'rate_frequency' => $frequency,
                ]);
            Log::info('Kkiapay setupPayoutRate', ['dest' => $destination, 'freq' => $frequency, 'http' => $resp->status(), 'body' => substr($resp->body(), 0, 500)]);
            if ($resp->successful()) return array_merge(['status' => 'SUCCESS', 'setup' => true], (array)$resp->json());
            return ['status' => 'FAILED', 'http_status' => $resp->status(), 'body' => $resp->body()];
        } catch (\Throwable $e) {
            return ['status' => 'FAILED', 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Solde du compte Kkiapay (available_balance, operation_balance, total_balance)
    // -------------------------------------------------------------------------
    public function getBalance(): array
    {
        if (!$this->isConfigured()) return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré'];

        $urls = [
            $this->baseUrl . '/api/v1/merchants/me',
            $this->baseUrl . '/api/v1/balance',
            $this->baseUrl . '/api/v1/accounts/balance',
        ];

        foreach ($urls as $url) {
            try {
                $resp = Http::withOptions($this->clientOptions())
                    ->withHeaders($this->headers())
                    ->get($url);
                Log::info("Kkiapay getBalance {$url}", ['http' => $resp->status(), 'body' => substr($resp->body(), 0, 500)]);
                if ($resp->successful() && is_array($resp->json())) {
                    return array_merge(['status' => 'SUCCESS', 'url' => $url], $resp->json());
                }
            } catch (\Throwable $e) {
                Log::warning("Kkiapay getBalance {$url} exception: " . $e->getMessage());
            }
        }
        return ['status' => 'FAILED', 'error' => 'Impossible de récupérer le solde Kkiapay'];
    }

    // -------------------------------------------------------------------------
    // Payout DIRECT vers un mobile money (Merchant Pro REQUIS en production).
    // Essaie plusieurs variantes d'endpoints connus.
    //
    // En sandbox clés standard : retourne FAILED avec le détail de la réponse Kkiapay
    // (pas de simulation silencieuse). Utilisez setupPayoutRoof à la place.
    // -------------------------------------------------------------------------
    public function payout(string $phoneNumber, float $amount, string $reference = '', ?string $beneficiaryName = null): array
    {
        $phoneNumber = $this->normalizePhone($phoneNumber);
        $ref = $reference !== '' ? $reference : 'PAYOUT-' . strtoupper(uniqid());

        if (!$this->isConfigured()) {
            if ($this->simulate) return $this->simulateResponse('no-config-payout', ['reference' => $ref, 'amount' => $amount, 'destination' => $phoneNumber]);
            return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré', 'reference' => $ref];
        }

        if ($amount <= 0) return ['status' => 'FAILED', 'error' => 'Montant invalide', 'reference' => $ref];

        $headers = $this->headers();
        $attempts = [];

        // Endpoint 1 : /api/v1/payouts (forme moderne)
        $endpoints = [
            [
                'url' => $this->baseUrl . '/api/v1/payouts',
                'payload' => [
                    'amount' => $amount,
                    'destination' => $phoneNumber,
                    'destination_type' => 'MOBILE_MONEY',
                    'reference' => $ref,
                    'narration' => "Payout transport {$ref}",
                ],
            ],
            [
                'url' => $this->baseUrl . '/api/v1/transactions/disburse',
                'payload' => [
                    'reference' => $ref,
                    'destination' => [
                        'type' => 'mobile_money',
                        'amount' => $amount,
                        'currency' => 'XOF',
                        'narration' => "Payout transport {$ref}",
                        'mobile_money' => [
                            // operator sera détecté par Kkiapay via le préfixe (MTN/MOOV au Bénin)
                            'mobile_number' => $phoneNumber,
                        ],
                        'customer' => [
                            'name' => $beneficiaryName ?: 'Transporteur',
                            'email' => '',
                        ],
                    ],
                ],
            ],
            [
                'url' => $this->baseUrl . '/api/v1/transfers',
                'payload' => [
                    'amount' => $amount,
                    'phone' => $phoneNumber,
                    'destination' => $phoneNumber,
                    'reference' => $ref,
                    'type' => 'mobile_money',
                ],
            ],
        ];

        foreach ($endpoints as $attempt) {
            try {
                $resp = Http::withOptions($this->clientOptions())
                    ->withHeaders($headers)
                    ->post($attempt['url'], $attempt['payload']);
                $body = $resp->body();
                $json = $resp->json();
                $log = ['url' => $attempt['url'], 'http' => $resp->status(), 'body' => substr($body, 0, 1000)];
                Log::info('Kkiapay payout attempt', $log);
                $attempts[] = $log;

                if ($resp->successful() && is_array($json)) {
                    // Certains endpoints retournent {status:"SUCCESS"}, d'autres {status:"success"}, d'autres {success:true}
                    $status = strtoupper((string)($json['status'] ?? ($json['success'] ?? false) ? 'SUCCESS' : 'PENDING'));
                    if (!empty($json['status'])) $status = strtoupper((string)$json['status']);
                    return array_merge([
                        'status' => $status,
                        'reference' => $ref,
                        'amount' => $amount,
                        'destination' => $phoneNumber,
                    ], $json);
                }

                // 401 = clés invalides
                if ($resp->status() === 401) {
                    if ($this->simulate) {
                        return $this->simulateResponse('401-payout', $log + ['reference' => $ref]);
                    }
                    return [
                        'status' => 'FAILED',
                        'error' => 'Clés API invalides (401)',
                        'reference' => $ref,
                        'amount' => $amount,
                        'destination' => $phoneNumber,
                        'http_status' => 401,
                        'attempts' => $attempts,
                    ];
                }

                // 402/403 = solde insuffisant ou compte non Pro
                if (in_array($resp->status(), [402, 403, 422], true)) {
                    // On continue pour essayer autre variante (parfois la forme du payload diffère)
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning('Kkiapay payout exception', ['url' => $attempt['url'], 'error' => $e->getMessage()]);
                $attempts[] = ['url' => $attempt['url'], 'error' => $e->getMessage()];
            }
        }

        if ($this->simulate) {
            return $this->simulateResponse('final-fallback-payout', ['reference' => $ref, 'amount' => $amount, 'destination' => $phoneNumber]);
        }

        return [
            'status' => 'FAILED',
            'error' => 'Payout non disponible (compte Merchant Pro probablement requis ou solde insuffisant)',
            'reference' => $ref,
            'amount' => $amount,
            'destination' => $phoneNumber,
            'attempts' => $attempts,
        ];
    }

    // -------------------------------------------------------------------------
    // Vérifier le statut d'un payout
    // -------------------------------------------------------------------------
    public function payoutStatus(string $reference): array
    {
        if (!$this->isConfigured()) return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré'];

        try {
            $resp = Http::withOptions($this->clientOptions())
                ->withHeaders($this->headers())
                ->get($this->baseUrl . '/api/v1/payouts/' . urlencode($reference));
            Log::info('Kkiapay payoutStatus', ['ref' => $reference, 'http' => $resp->status(), 'body' => substr($resp->body(), 0, 500)]);
            if ($resp->successful() && is_array($resp->json())) {
                return array_merge(['status' => 'SUCCESS'], $resp->json());
            }
        } catch (\Throwable $e) {
            Log::warning('Kkiapay payoutStatus exception: ' . $e->getMessage());
        }
        return ['status' => 'UNKNOWN', 'reference' => $reference];
    }

    // -------------------------------------------------------------------------
    // Liste des opérations (transactions/payouts) pour admin
    // -------------------------------------------------------------------------
    public function listTransactions(?string $from = null, ?string $to = null, int $page = 1, int $limit = 50): array
    {
        if (!$this->isConfigured()) return ['status' => 'FAILED', 'error' => 'Kkiapay non configuré'];

        $params = ['page' => $page, 'per_page' => $limit, 'limit' => $limit];
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $urls = [
            $this->baseUrl . '/api/v1/transactions',
            $this->baseUrl . '/api/v1/transactions/list',
        ];
        foreach ($urls as $url) {
            try {
                $resp = Http::withOptions($this->clientOptions())->withHeaders($this->headers())->get($url, $params);
                if ($resp->successful() && is_array($resp->json())) {
                    return array_merge(['status' => 'SUCCESS'], $resp->json());
                }
            } catch (\Throwable $e) {
                Log::warning("Kkiapay listTransactions {$url}: " . $e->getMessage());
            }
        }
        return ['status' => 'FAILED', 'error' => 'Impossible de récupérer les transactions Kkiapay'];
    }

    // -------------------------------------------------------------------------
    // Validation téléphone (Bénin par défaut : 229XXXXXXXXX)
    // -------------------------------------------------------------------------
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '') return '';
        // Retirer le + si présent (déjà géré par preg_replace)
        // Bénin: numéro à 8 chiffres -> préfixer 229
        if (strlen($phone) === 8 && !str_starts_with($phone, '229')) {
            $phone = '229' . $phone;
        }
        // Si 10 chiffres commençant par 0 -> retirer le 0 puis préfixer 229
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '229' . substr($phone, 1);
        }
        // Si 10 chiffres Bénin direct sans 229, préfixer
        if (strlen($phone) === 8 && ctype_digit($phone)) {
            $phone = '229' . $phone;
        }
        // Ne pas préfixer si numéro a déjà un autre indicatif international (commençant par autre que 229 et > 8 chiffres)
        return $phone;
    }

    /**
     * Détecte l'opérateur Bénin à partir du préfixe
     * MTN Bénin : 97, 96, 57
     * MOOV Bénin : 95, 99, 98, 54
     * Celtiis : 40, 42, 44
     */
    public static function detectOperatorBenin(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '229')) $phone = substr($phone, 3);
        if (strlen($phone) < 2) return 'UNKNOWN';
        $prefix = substr($phone, 0, 2);
        return match ($prefix) {
            '97', '96', '57', '56', '51' => 'MTN',
            '95', '99', '98', '54', '55' => 'MOOV',
            '40', '42', '44', '43' => 'CELTIIS',
            default => 'UNKNOWN',
        };
    }
}
