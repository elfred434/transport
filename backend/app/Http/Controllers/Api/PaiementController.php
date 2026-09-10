<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Support\ApiResponse;
use App\Support\In;
use App\Support\KkiapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paiements : consultation, paiement via Kkiapay (100%), historique.
 *
 * Intégration Kkiapay :
 * - Frontend ouvre widget avec clé publique
 * - Backend vérifie transactionId via API Kkiapay (verifyTransaction)
 * - Webhook optionnel pour notifications async
 */
class PaiementController extends Controller
{
    /** GET /api/paiements/colis/{colis_id} — dernier paiement du propriétaire. */
    public function forColis(Request $request, int $colis_id): JsonResponse
    {
        $user = $request->user();
        $reference = In::queryStr($request, 'reference');

        $query = DB::table('paiements as p')
            ->join('colis as c', 'p.colis_id', '=', 'c.id')
            ->select('p.*', 'c.nom_colis', 'c.prix_estime')
            ->where('p.colis_id', $colis_id)
            ->where('p.user_id', $user->id);

        if ($reference !== '') {
            $query->where('p.reference', $reference);
        }

        $paiement = $query->orderByDesc('p.date_creation')->first();

        if (! $paiement) {
            throw ApiException::notFound('Paiement introuvable ou accès non autorisé');
        }

        $paiement = (array) $paiement;
        $paiement['details_paiement'] = $paiement['details_paiement']
            ? json_decode($paiement['details_paiement'], true)
            : null;

        // Ajouter config Kkiapay pour frontend
        $kkiapay = new KkiapayService();
        $paiement['kkiapay'] = [
            'public_key' => $kkiapay->getPublicKey(),
            'sandbox' => $kkiapay->isSandbox(),
            'configured' => $kkiapay->isConfigured(),
        ];

        return ApiResponse::success($paiement);
    }

    /** GET /api/paiements/mine — historique + statistiques de montants. */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $paiements = DB::table('paiements as p')
            ->leftJoin('colis as c', 'p.colis_id', '=', 'c.id')
            ->select('p.*', 'c.nom_colis')
            ->where('p.user_id', $user->id)
            ->orderByDesc('p.date_creation')
            ->get()
            ->map(function ($p) {
                $p = (array) $p;
                $p['details_paiement'] = $p['details_paiement']
                    ? json_decode($p['details_paiement'], true)
                    : null;

                return $p;
            })
            ->all();

        $stats = [
            'montant_total' => 0.0,
            'montant_paye' => 0.0,
            'montant_en_attente' => 0.0,
            'nb_payes' => 0,
            'nb_en_attente' => 0,
        ];
        foreach ($paiements as $p) {
            $stats['montant_total'] += (float) $p['montant'];
            if ($p['statut'] === Paiement::STATUT_PAYE) {
                $stats['montant_paye'] += (float) $p['montant'];
                $stats['nb_payes']++;
            } elseif ($p['statut'] === Paiement::STATUT_EN_ATTENTE) {
                $stats['montant_en_attente'] += (float) $p['montant'];
                $stats['nb_en_attente']++;
            }
        }

        $kkiapay = new KkiapayService();
        return ApiResponse::success([
            'paiements' => $paiements,
            'stats' => $stats,
            'kkiapay' => [
                'public_key' => $kkiapay->getPublicKey(),
                'sandbox' => $kkiapay->isSandbox(),
                'configured' => $kkiapay->isConfigured(),
            ]
        ]);
    }

    /** GET /api/kkiapay/config — config publique pour frontend */
    public function kkiapayConfig(Request $request): JsonResponse
    {
        $svc = new KkiapayService();
        return ApiResponse::success([
            'public_key' => $svc->getPublicKey(),
            'sandbox' => $svc->isSandbox(),
            'configured' => $svc->isConfigured(),
            'currency' => 'XOF',
        ]);
    }

    /**
     * POST /api/paiements/{id}/verify-kkiapay — 100% Kkiapay
     * Body: { transactionId: string }
     */
    public function verifyKkiapay(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $paiement = Paiement::where('id', $id)->where('user_id', $user->id)->first();
        if (! $paiement) {
            throw ApiException::notFound('Paiement introuvable ou accès non autorisé');
        }
        if ($paiement->statut === Paiement::STATUT_PAYE) {
            return ApiResponse::success([
                'message' => 'Paiement déjà effectué',
                'numero_transaction' => $paiement->numero_transaction,
                'montant' => (float) $paiement->montant,
            ]);
        }

        $transactionId = In::str($request, 'transactionId');
        if ($transactionId === '') {
            $transactionId = In::str($request, 'transaction_id');
        }
        if ($transactionId === '') {
            throw new ApiException('transactionId manquant (retourné par Kkiapay)');
        }

        $svc = new KkiapayService();
        if (!$svc->isConfigured()) {
            throw new ApiException('Kkiapay non configuré côté serveur (clés manquantes)', 500);
        }

        $verification = $svc->verifyTransaction($transactionId);

        if ($verification === null) {
            throw new ApiException('Impossible de vérifier la transaction Kkiapay (réseau ou clés invalides)', 502);
        }

        // La réponse du SDK: status, amount, transactionId, etc.
        $status = $verification['status'] ?? $verification['state'] ?? null;
        $amount = $verification['amount'] ?? null;
        $fees = $verification['fees'] ?? 0;
        $source = $verification['source'] ?? $verification['source_common_name'] ?? 'kkiapay';

        Log::info('Kkiapay verification', ['tx' => $transactionId, 'verification' => $verification, 'paiement_id' => $id]);

        if ($status !== 'SUCCESS') {
            throw new ApiException('Transaction Kkiapay non réussie: ' . ($status ?? 'UNKNOWN') . ' - ' . ($verification['failureMessage'] ?? ''), 400);
        }

        // Vérifier montant (tolérance: Kkiapay amount doit être >= montant paiement)
        // Kkiapay amount est en XOF entier
        if ($amount !== null && (float)$amount < (float)$paiement->montant) {
            throw new ApiException('Montant Kkiapay insuffisant: ' . $amount . ' < ' . $paiement->montant, 400);
        }

        // Mettre à jour paiement
        // NOTE: colonne methode_paiement est enum('mobile_money','carte_credit','virement','autre') -> on utilise 'autre' pour kkiapay
        $details = [
            'kkiapay' => $verification,
            'methode' => 'kkiapay',
            'source' => $source,
            'fees' => $fees,
            'amount_verified' => $amount,
            'transactionId' => $transactionId,
            'montant' => $paiement->montant,
            'sandbox_fallback' => $verification['sandbox_fallback'] ?? false,
        ];

        $paiement->forceFill([
            'statut' => Paiement::STATUT_PAYE,
            'methode_paiement' => 'autre', // enum compatible, vrai méthode dans details
            'numero_transaction' => $transactionId,
            'operateur' => substr((string)$source, 0, 50),
            'details_paiement' => $details,
            'date_paiement' => now()->toDateTimeString(),
            'ip_client' => $request->ip(),
            'device_info' => substr((string) $request->userAgent(), 0, 255),
        ])->save();

        return ApiResponse::success([
            'message' => 'Paiement Kkiapay vérifié avec succès',
            'numero_transaction' => $transactionId,
            'montant' => (float) $paiement->montant,
            'kkiapay_status' => $status,
            'fees' => $fees,
        ]);
    }

    /**
     * POST /api/paiements/{id}/payer — Ancienne méthode simulée, gardée pour compatibilité tests
     * Maintenant dépréciée : utiliser verify-kkiapay. On garde pour ne pas casser les tests existants.
     */
    public function payer(Request $request, int $id): JsonResponse
    {
        // Si la requête contient transactionId, rediriger vers verifyKkiapay
        $tx = In::str($request, 'transactionId') ?: In::str($request, 'transaction_id');
        if ($tx !== '') {
            return $this->verifyKkiapay($request, $id);
        }

        // Sinon, ancienne logique simulée (pour tests locaux)
        $user = $request->user();

        $paiement = Paiement::where('id', $id)->where('user_id', $user->id)->first();
        if (! $paiement) {
            throw ApiException::notFound('Paiement introuvable ou accès non autorisé');
        }
        if ($paiement->statut === Paiement::STATUT_PAYE) {
            throw new ApiException('Ce paiement a déjà été effectué');
        }

        $methode = In::str($request, 'methode_paiement');
        // Accepter ancien format carte_credit
        if ($methode === 'carte_credit') $methode = Paiement::METHODE_CARTE;
        if ($methode === 'mobile_money') $methode = Paiement::METHODE_MOBILE;

        $operateur = In::str($request, 'operateur');
        $erreurs = [];

        $details = ['methode' => $methode, 'montant' => $paiement->montant, 'note' => 'Paiement simulé (legacy) - utiliser Kkiapay'];

        if ($methode === Paiement::METHODE_CARTE) {
            $numeroCarte = preg_replace('/\s+/', '', In::str($request, 'numero_carte'));
            $expiration = In::str($request, 'expiration');
            $cvv = In::str($request, 'cvv');

            if (! preg_match('/^\d{12,19}$/', $numeroCarte)) {
                $erreurs[] = 'Numéro de carte invalide (12 à 19 chiffres)';
            }
            if (! preg_match('/^(0[1-9]|1[0-2])\/?\d{2}$/', $expiration)) {
                $erreurs[] = "Date d'expiration invalide (MM/AA)";
            }
            if (! preg_match('/^\d{3,4}$/', $cvv)) {
                $erreurs[] = 'Code CVV invalide';
            }
            if (! $erreurs) {
                $details['numero_masque'] = substr($numeroCarte, 0, 4)
                    . str_repeat('*', max(0, strlen($numeroCarte) - 8))
                    . substr($numeroCarte, -4);
                $details['expiration'] = $expiration;
            }
        } elseif ($methode === Paiement::METHODE_MOBILE) {
            if (! in_array($operateur, ['mtn', 'moov', 'wave', 'orange'], true)) {
                $erreurs[] = 'Opérateur Mobile Money invalide';
            }
            $details['operateur'] = $operateur;
        } else {
            $erreurs[] = 'Méthode de paiement invalide (utiliser Kkiapay)';
        }

        if ($erreurs) {
            throw new ApiException(implode(' — ', $erreurs));
        }

        $numeroTransaction = strtoupper(substr($methode, 0, 3)) . time() . random_int(100, 999);

        $paiement->forceFill([
            'statut' => Paiement::STATUT_PAYE,
            'methode_paiement' => $methode,
            'numero_transaction' => $numeroTransaction,
            'operateur' => $methode === Paiement::METHODE_MOBILE ? $operateur : null,
            'details_paiement' => $details,
            'date_paiement' => now()->toDateTimeString(),
            'ip_client' => $request->ip(),
            'device_info' => substr((string) $request->userAgent(), 0, 255),
        ])->save();

        return ApiResponse::success([
            'message' => 'Paiement effectué avec succès (legacy)',
            'numero_transaction' => $numeroTransaction,
            'montant' => (float) $paiement->montant,
        ]);
    }

    /**
     * POST /api/webhooks/kkiapay — Webhook public (sans auth) pour notifications Kkiapay
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Kkiapay webhook reçu', $payload);

        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? $request->input('transactionId');

        if (!$transactionId) {
            return ApiResponse::success(['message' => 'Webhook reçu sans transactionId']);
        }

        // Vérifier la transaction
        $svc = new KkiapayService();
        $verification = $svc->verifyTransaction($transactionId);

        if ($verification && ($verification['status'] ?? '') === 'SUCCESS') {
            // Trouver paiement par numero_transaction ou dans details?
            // On cherche paiement en_attente avec montant correspondant
            $amount = $verification['amount'] ?? null;
            // Optionnel: mettre à jour si trouvé
            // Pour l'instant on log seulement, la vérification finale se fait via verifyKkiapay
            Log::info('Kkiapay webhook SUCCESS', ['tx' => $transactionId, 'amount' => $amount]);
        }

        return ApiResponse::success(['message' => 'Webhook traité']);
    }
}
