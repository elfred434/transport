<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Transporteur;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Avis : consulter les avis publiés d'un transporteur, déposer un avis.
 *
 * Port fidèle du contrôleur `avis.php` de l'API d'origine.
 */
class AvisController extends Controller
{
    /** GET /api/transporteurs/{id}/avis — avis approuvés uniquement. */
    public function forTransporteur(Request $request, int $id): JsonResponse
    {
        $avis = DB::table('avis as a')
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->select('a.*', 'u.nom', 'u.prenom', 'u.photo_profil')
            ->where('a.transporteur_id', $id)
            ->where('a.statut', Avis::STATUT_APPROUVE)
            ->orderByDesc('a.date_avis')
            ->get()
            ->map(function ($a) {
                $a = (array) $a;
                $a['photo_url'] = Files::url($a['photo_profil']);
                unset($a['photo_profil']);

                return $a;
            })
            ->all();

        return ApiResponse::success($avis);
    }

    /** POST /api/avis — dépôt d'un avis (modéré avant publication). */
    public function store(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;

        $transporteurId = In::int($request, 'transporteur_id');
        $note = In::int($request, 'note');
        $commentaire = In::str($request, 'commentaire');
        $colisId = In::int($request, 'colis_id') ?: null;

        // Garde d'auto-évaluation : le transporteur doit exister et ne pas être
        // l'utilisateur lui-même (message historique « Transporteur invalide »).
        if ($transporteurId <= 0 || $transporteurId === $uid) {
            throw new ApiException('Transporteur invalide');
        }
        if ($note < 1 || $note > 5) {
            throw new ApiException('La note doit être entre 1 et 5 étoiles');
        }
        if ($commentaire === '' || mb_strlen($commentaire) > 2000) {
            throw new ApiException('Le commentaire est obligatoire (2000 caractères max)');
        }

        if (! Transporteur::where('user_id', $transporteurId)->exists()) {
            throw ApiException::notFound('Cet utilisateur n\'est pas transporteur');
        }

        // Un seul avis par couple (utilisateur, transporteur).
        if (Avis::where('user_id', $uid)->where('transporteur_id', $transporteurId)->exists()) {
            throw ApiException::conflict('Vous avez déjà posté un avis pour ce transporteur');
        }

        Avis::create([
            'transporteur_id' => $transporteurId,
            'user_id' => $uid,
            'colis_id' => $colisId,
            'note' => $note,
            'commentaire' => $commentaire,
            'statut' => Avis::STATUT_EN_ATTENTE,
        ]);

        return ApiResponse::success(
            ['message' => 'Votre avis a été soumis et sera publié après modération'],
            201
        );
    }
}
