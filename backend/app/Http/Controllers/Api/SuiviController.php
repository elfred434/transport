<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\SuiviColis;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suivi : consultation par numéro de suivi, ajout d'étapes.
 *
 * Port fidèle du contrôleur `suivi.php` de l'API d'origine.
 */
class SuiviController extends Controller
{
    /** GET /api/suivi/{numero} */
    public function show(Request $request, string $numero): JsonResponse
    {
        $colis = DB::table('colis as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->select('c.*', 'u.nom', 'u.prenom')
            ->where('c.numero_suivi', $numero)
            ->first();

        if (! $colis) {
            throw ApiException::notFound('Colis introuvable pour ce numéro de suivi');
        }
        $colis = (array) $colis;

        // Étapes visibles : un « Livré » non confirmé par l'admin reste masqué.
        $etapes = DB::table('suivi_colis')
            ->select('statut', 'date_etape')
            ->where('colis_id', $colis['id'])
            ->where(function ($q) {
                $q->where('confirme_par_admin', true)->orWhere('statut', '!=', 'Livré');
            })
            ->orderBy('date_etape')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $dernier = DB::table('suivi_colis')
            ->where('colis_id', $colis['id'])
            ->orderByDesc('date_etape')
            ->value('statut') ?: 'En attente';

        $colis['image_url'] = Files::url($colis['image_colis'] ?? null);
        unset($colis['image_colis']);

        return ApiResponse::success([
            'colis' => $colis,
            'etapes' => $etapes,
            'statut_actuel' => $dernier,
        ]);
    }

    /**
     * POST /api/suivi — ajout d'une étape.
     *
     * Réservé à l'admin ou au transporteur affecté au colis (réservation sur
     * l'un de ses voyages). Un « Livré » posé par un transporteur crée une
     * demande de confirmation pour l'admin au lieu de clore le suivi.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $colisId = In::int($request, 'colis_id');
        $numero = In::str($request, 'numero_suivi');
        $statut = In::str($request, 'statut');

        if (! in_array($statut, ['En attente', 'En cours', 'Livré'], true)) {
            throw new ApiException('Statut invalide');
        }

        $colis = $colisId > 0
            ? Colis::find($colisId, ['id'])
            : Colis::where('numero_suivi', $numero)->first(['id']);

        if (! $colis) {
            throw ApiException::notFound('Colis introuvable');
        }
        $colisId = (int) $colis->id;

        $isAdmin = $user->isAdmin();
        if (! $isAdmin && ! $this->estTransporteurDuColis($colisId, (int) $user->id)) {
            throw ApiException::forbidden('Vous n\'êtes pas autorisé à modifier le suivi de ce colis');
        }

        // Un colis déjà livré ET confirmé par l'admin est immuable.
        $last = DB::table('suivi_colis')
            ->select('statut', 'confirme_par_admin')
            ->where('colis_id', $colisId)
            ->orderByDesc('date_etape')
            ->first();

        if ($last && $last->statut === 'Livré' && $last->confirme_par_admin) {
            throw new ApiException('Impossible de modifier le statut d\'un colis déjà livré et confirmé');
        }

        if ($statut === 'Livré' && ! $isAdmin) {
            $etape = SuiviColis::create([
                'colis_id' => $colisId,
                'statut' => $statut,
                'demande_livraison' => true,
            ]);

            // Signal admin : log + insertion dans notifications_admin si table existe
            $colisInfo = DB::table('colis')->where('id',$colisId)->select('nom_colis','numero_suivi')->first();
            $nomColis = $colisInfo ? $colisInfo->nom_colis : ('colis #'.$colisId);
            $msg = "📦 Livraison signalée : {$nomColis} par transporteur #{$user->id} — à confirmer";
            Log::info('📦 LIVRAISON SIGNAL', [
                'colis_id' => $colisId,
                'suivi_id' => $etape->id,
                'transporteur_id' => $user->id,
                'nom_colis' => $nomColis,
            ]);
            try {
                DB::table('notifications_admin')->insert([
                    'type' => 'livraison',
                    'colis_id' => $colisId,
                    'suivi_id' => $etape->id,
                    'transporteur_id' => $user->id,
                    'message' => $msg,
                    'lu' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Impossible insérer notif admin (table absente?): '.$e->getMessage());
            }

            return ApiResponse::success(
                ['message' => 'Demande de confirmation de livraison envoyée à l\'administrateur'],
                201
            );
        }

        SuiviColis::create(['colis_id' => $colisId, 'statut' => $statut]);

        return ApiResponse::success(['message' => 'Étape de suivi ajoutée'], 201);
    }

    /**
     * L'utilisateur est-il le transporteur affecté à ce colis (via une
     * réservation sur l'un de ses voyages) ? Équivalent de
     * is_transporteur_of_colis().
     */
    private function estTransporteurDuColis(int $colisId, int $userId): bool
    {
        return DB::table('reservations as r')
            ->join('voyages as v', 'v.id', '=', 'r.voyage_id')
            ->where('r.colis_id', $colisId)
            ->where('v.user_id', $userId)
            ->exists();
    }
}
