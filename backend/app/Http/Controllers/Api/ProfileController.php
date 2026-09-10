<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Transporteur;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Profil personnel, fiche publique transporteur, statistiques transporteur.
 *
 * Port fidèle du contrôleur `users.php` de l'API d'origine.
 */
class ProfileController extends Controller
{
    /** GET /api/profile — {user, transporteur|null}. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $transporteur = Transporteur::where('user_id', $user->id)->first();
        $transporteurPublic = $transporteur ? $transporteur->toPublicArray() : null;

        return ApiResponse::success([
            'user' => $user->toPublicArray(),
            'transporteur' => $transporteurPublic,
        ]);
    }

    /** POST /api/profile — mise à jour du profil (photo optionnelle). */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $nom = In::str($request, 'nom');
        $prenom = In::str($request, 'prenom');
        $email = In::str($request, 'email');
        $telephone = In::str($request, 'telephone');

        if ($nom === '' || $prenom === '') {
            throw new ApiException('Le nom et le prénom sont obligatoires');
        }

        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Adresse email invalide');
        }

        $conflit = User::where('email', $email)->where('id', '!=', $user->id)->exists();
        if ($conflit) {
            throw ApiException::conflict('Cet email est déjà utilisé par un autre compte');
        }

        $photo = Uploads::storeImage($request->file('photo_profil'), '', 'profil_' . $user->id);

        $user->nom = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
        $user->prenom = htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8');
        $user->email = $email;
        $user->telephone = htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8');
        if ($photo) {
            $user->photo_profil = $photo;
        }
        $user->save();

        return ApiResponse::success(['user' => $user->fresh()->toPublicArray()]);
    }

    /**
     * GET /api/transporteurs/{id} — fiche publique d'un transporteur.
     *
     * `id` est l'identifiant UTILISATEUR (users.id), comme dans l'API d'origine.
     * Le solde et les coordonnées du transporteur ne sont jamais exposés ici.
     */
    public function transporteur(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            throw ApiException::notFound('Utilisateur introuvable');
        }

        $fiche = Transporteur::where('user_id', $id)->first();
        if (! $fiche) {
            throw ApiException::notFound('Cet utilisateur n\'est pas transporteur');
        }

        $transporteur = $fiche->toPublicArray();
        unset($transporteur['solde']);

        $stats = DB::table('avis')
            ->selectRaw('AVG(note) AS note_moyenne, COUNT(*) AS nb_avis')
            ->where('transporteur_id', $id)
            ->where('statut', 'approuve')
            ->first();

        $nbColis = (int) DB::table('reservations as r')
            ->whereIn('r.statut', ['accepte','termine'])
            ->whereIn('r.voyage_id', DB::table('voyages')->select('id')->where('user_id', $id))
            ->count();

        $voyages = DB::table('voyages')
            ->where('user_id', $id)
            ->where('statut', 'approuve')
            ->whereDate('date_depart', '>=', now()->toDateString())
            ->orderBy('date_depart')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success([
            // Identité publique réduite : ni email ni téléphone.
            'user' => [
                'id' => (int) $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'photo_url' => Files::url($user->photo_profil),
                'date_inscription' => $user->getRawOriginal('date_inscription'),
            ],
            'transporteur' => $transporteur,
            'stats' => [
                'note_moyenne' => $stats && $stats->note_moyenne !== null
                    ? round((float) $stats->note_moyenne, 2)
                    : null,
                'nb_avis' => (int) ($stats->nb_avis ?? 0),
                'nb_colis_transportes' => $nbColis,
            ],
            'voyages' => $voyages,
        ]);
    }

    /** GET /api/transporteur-stats — statistiques du transporteur connecté. */
    public function stats(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;

        // Un utilisateur sans fiche transporteur obtient des statistiques vides
        // (solde 0) plutôt qu'une erreur — comportement d'origine.
        $solde = (float) (Transporteur::where('user_id', $uid)->value('solde') ?: 0);

        $nbVoyages = (int) DB::table('voyages')->where('user_id', $uid)->count();

        $nbReservations = (int) DB::table('reservations as r')
            ->join('voyages as v', 'v.id', '=', 'r.voyage_id')
            ->where('v.user_id', $uid)
            ->whereIn('r.statut', ['accepte','termine'])
            ->count();

        $avis = DB::table('avis')
            ->selectRaw('AVG(note) AS note_moyenne, COUNT(*) AS nb_avis')
            ->where('transporteur_id', $uid)
            ->where('statut', 'approuve')
            ->first();

        return ApiResponse::success([
            'solde' => $solde,
            'nb_voyages' => $nbVoyages,
            'nb_reservations_acceptees' => $nbReservations,
            'note_moyenne' => $avis && $avis->note_moyenne !== null
                ? round((float) $avis->note_moyenne, 2)
                : null,
            'nb_avis' => (int) ($avis->nb_avis ?? 0),
        ]);
    }
}
