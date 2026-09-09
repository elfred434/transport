<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Support\ApiResponse;
use App\Support\In;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Paiements : consultation, paiement simulé (carte / mobile money), historique.
 *
 * Port fidèle du contrôleur `paiements.php` de l'API d'origine.
 *
 * Aucune donnée sensible n'est persistée : pour une carte, seul un numéro
 * masqué et la date d'expiration sont conservés dans `details_paiement`.
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
        ];
        foreach ($paiements as $p) {
            $stats['montant_total'] += (float) $p['montant'];
            if ($p['statut'] === Paiement::STATUT_PAYE) {
                $stats['montant_paye'] += (float) $p['montant'];
            } elseif ($p['statut'] === Paiement::STATUT_EN_ATTENTE) {
                $stats['montant_en_attente'] += (float) $p['montant'];
            }
        }

        return ApiResponse::success(['paiements' => $paiements, 'stats' => $stats]);
    }

    /**
     * POST /api/paiements/{id}/payer — paiement simulé.
     *
     * Les erreurs de validation sont accumulées puis renvoyées en une seule
     * fois, séparées par « — » (comportement historique, vérifié par les tests).
     */
    public function payer(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $paiement = Paiement::where('id', $id)->where('user_id', $user->id)->first();
        if (! $paiement) {
            throw ApiException::notFound('Paiement introuvable ou accès non autorisé');
        }
        if ($paiement->statut === Paiement::STATUT_PAYE) {
            throw new ApiException('Ce paiement a déjà été effectué');
        }

        $methode = In::str($request, 'methode_paiement');
        $operateur = In::str($request, 'operateur');
        $erreurs = [];

        $details = ['methode' => $methode, 'montant' => $paiement->montant];

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
                // Aucune donnée sensible stockée : uniquement un numéro masqué.
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
            $erreurs[] = 'Méthode de paiement invalide';
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
            'message' => 'Paiement effectué avec succès',
            'numero_transaction' => $numeroTransaction,
            'montant' => (float) $paiement->montant,
        ]);
    }
}
