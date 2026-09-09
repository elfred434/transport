<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administration : statistiques, gestion des utilisateurs, colis, voyages,
 * transporteurs, paiements, avis et messages de contact.
 *
 * Toutes les routes de ce contrôleur passent par le middleware `admin`
 * (équivalent de require_admin()).
 *
 * Port fidèle du contrôleur `admin.php` de l'API d'origine, y compris la
 * sémantique « rowCount » des mutations : une mise à jour qui ne change aucune
 * ligne (statut déjà identique) ou une suppression sans cible renvoie 404.
 */
class AdminController extends Controller
{
    private const STATUTS_MODERATION = ['en_attente', 'approuve', 'refuse'];
    private const STATUTS_PAIEMENT = ['en_attente', 'paye', 'echec', 'annule'];
    private const ROLES = ['utilisateur', 'transporteur', 'admin'];

    /** GET /api/admin/stats */
    public function stats(): JsonResponse
    {
        $stats = [
            'nb_users' => (int) DB::table('users')->count(),
            'nb_colis' => (int) DB::table('colis')->count(),
            'nb_voyages' => (int) DB::table('voyages')->count(),
            'nb_transporteurs' => (int) DB::table('transporteurs')->count(),
            'nb_paiements' => (int) DB::table('paiements')->count(),
            'nb_avis' => (int) DB::table('avis')->count(),
            'colis_en_attente' => (int) DB::table('colis')->where('statut', 'en_attente')->count(),
            'voyages_en_attente' => (int) DB::table('voyages')->where('statut', 'en_attente')->count(),
            'avis_en_attente' => (int) DB::table('avis')->where('statut', 'en_attente')->count(),
            // Demandes de livraison en attente, uniquement si l'étape demandée
            // est bien la DERNIÈRE du colis.
            'demandes_livraison' => (int) DB::table('suivi_colis as s')
                ->where('s.demande_livraison', 1)
                ->where('s.confirme_par_admin', 0)
                ->where('s.statut', 'Livré')
                ->whereRaw('s.date_etape = (SELECT MAX(s2.date_etape) FROM suivi_colis s2 WHERE s2.colis_id = s.colis_id)')
                ->count(),
            'montant_total_paye' => (float) DB::table('paiements')->where('statut', 'paye')->sum('montant'),
            'messages_contact_non_lus' => (int) DB::table('messages_contact')->where('lu_par_admin', 0)->count(),
        ];

        return ApiResponse::success($stats);
    }

    // -----------------------------------------------------------------------
    // Utilisateurs
    // -----------------------------------------------------------------------

    /** GET /api/admin/users */
    public function users(Request $request): JsonResponse
    {
        $search = In::queryStr($request, 'search');
        $role = In::queryStr($request, 'role');

        $query = DB::table('users')
            ->select('id', 'nom', 'prenom', 'email', 'telephone', 'role', 'photo_profil', 'date_inscription');

        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('nom', 'like', $like)
                  ->orWhere('prenom', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }
        if (in_array($role, self::ROLES, true)) {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('date_inscription')->limit(200)->get()
            ->map(function ($u) {
                $u = (array) $u;
                $u['photo_url'] = Files::url($u['photo_profil']);
                unset($u['photo_profil']);

                return $u;
            })
            ->all();

        return ApiResponse::success($users);
    }

    /** POST /api/admin/users */
    public function userCreate(Request $request): JsonResponse
    {
        $nom = In::str($request, 'nom');
        $prenom = In::str($request, 'prenom');
        $email = In::str($request, 'email');
        $password = In::str($request, 'password');
        $role = In::str($request, 'role');

        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Adresse email invalide');
        }
        if (strlen($password) < 8) {
            throw new ApiException('Le mot de passe doit contenir au moins 8 caractères');
        }
        if ($nom === '' || $prenom === '') {
            throw new ApiException('Le nom et le prénom sont obligatoires');
        }
        if (! in_array($role, self::ROLES, true)) {
            throw new ApiException('Rôle invalide');
        }
        if (User::where('email', $email)->exists()) {
            throw ApiException::conflict('Cet email est déjà utilisé');
        }

        $id = User::create([
            'nom' => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
            'prenom' => htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ])->id;

        return ApiResponse::success(['message' => 'Utilisateur créé', 'id' => (int) $id], 201);
    }

    /** DELETE /api/admin/users/{id} */
    public function userDelete(Request $request, int $id): JsonResponse
    {
        if ($id === (int) $request->user()->id) {
            throw new ApiException('Impossible de supprimer votre propre compte');
        }

        if (User::where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Utilisateur introuvable');
        }

        return ApiResponse::success(['message' => 'Utilisateur supprimé']);
    }

    // -----------------------------------------------------------------------
    // Colis + suivi
    // -----------------------------------------------------------------------

    /** GET /api/admin/colis */
    public function colis(Request $request): JsonResponse
    {
        $statut = In::queryStr($request, 'statut');
        $search = In::queryStr($request, 'search');

        $query = DB::table('colis as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->select('c.*', 'u.nom', 'u.prenom', 'u.email')
            ->selectSub(
                DB::table('suivi_colis as s')
                    ->select('s.id')
                    ->whereColumn('s.colis_id', 'c.id')
                    ->where('s.demande_livraison', 1)
                    ->where('s.confirme_par_admin', 0)
                    ->orderByDesc('s.date_etape')
                    ->limit(1),
                'demande_livraison_id'
            )
            ->selectSub(
                DB::table('suivi_colis as s2')
                    ->select('s2.statut')
                    ->whereColumn('s2.colis_id', 'c.id')
                    ->orderByDesc('s2.date_etape')
                    ->limit(1),
                'statut_livraison'
            );

        if (in_array($statut, self::STATUTS_MODERATION, true)) {
            $query->where('c.statut', $statut);
        }
        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('c.nom_colis', 'like', $like)
                  ->orWhere('c.numero_suivi', 'like', $like)
                  ->orWhere('c.pays', 'like', $like)
                  ->orWhere('c.ville', 'like', $like);
            });
        }

        $colis = $query->orderByDesc('c.date_post')->limit(200)->get()
            ->map(function ($c) {
                $c = (array) $c;
                $c['image_url'] = Files::url($c['image_colis'] ?? null);
                unset($c['image_colis']);

                return $c;
            })
            ->all();

        return ApiResponse::success($colis);
    }

    /** POST /api/admin/colis/{id}/statut */
    public function colisStatut(Request $request, int $id): JsonResponse
    {
        $statut = In::str($request, 'statut');
        if (! in_array($statut, self::STATUTS_MODERATION, true)) {
            throw new ApiException('Statut invalide');
        }

        // Sémantique d'origine : 0 ligne modifiée (inexistant OU statut déjà
        // identique) est traité comme un 404.
        $changed = DB::table('colis')->where('id', $id)->update(['statut' => $statut]);
        if ($changed === 0) {
            throw ApiException::notFound('Colis introuvable');
        }

        return ApiResponse::success(['message' => "Statut du colis mis à jour ($statut)"]);
    }

    /** DELETE /api/admin/colis/{id} */
    public function colisDelete(int $id): JsonResponse
    {
        if (DB::table('colis')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Colis introuvable');
        }

        return ApiResponse::success(['message' => 'Colis supprimé']);
    }

    /**
     * POST /api/admin/suivi/{id}/livraison — décision sur une demande de
     * livraison (decision = confirmer | refuser).
     *
     * Confirmation (atomique) : étape validée, réservations acceptées du colis
     * passées à `termine`, commission de 5 % du prix estimé créditée au solde
     * du transporteur affecté.
     */
    public function livraisonDecision(Request $request, int $id): JsonResponse
    {
        $decision = In::str($request, 'decision');

        $etape = DB::table('suivi_colis')
            ->where('id', $id)
            ->where('demande_livraison', 1)
            ->first();

        if (! $etape) {
            throw ApiException::notFound('Demande de livraison introuvable');
        }

        if ($decision === 'confirmer') {
            try {
                $commission = DB::transaction(function () use ($etape, $id) {
                    DB::table('suivi_colis')->where('id', $id)
                        ->update(['confirme_par_admin' => 1, 'demande_livraison' => 0]);

                    // Réservations acceptées du colis → termine.
                    DB::table('reservations')
                        ->where('colis_id', $etape->colis_id)
                        ->where('statut', 'accepte')
                        ->update(['statut' => 'termine']);

                    // Transporteur affecté (première réservation terminée).
                    $info = DB::table('colis as c')
                        ->join('reservations as r', function ($join) {
                            $join->on('r.colis_id', '=', 'c.id')->where('r.statut', '=', 'termine');
                        })
                        ->join('voyages as v', 'v.id', '=', 'r.voyage_id')
                        ->select('c.prix_estime', 'v.user_id as transporteur_id')
                        ->where('c.id', $etape->colis_id)
                        ->first();

                    $commission = 0.0;
                    if ($info) {
                        $commission = Pricing::commission((float) $info->prix_estime);
                        DB::table('transporteurs')
                            ->where('user_id', $info->transporteur_id)
                            ->increment('solde', $commission);
                    }

                    return $commission;
                });
            } catch (\Throwable $e) {
                Log::error('Erreur confirmation livraison : ' . $e->getMessage());
                throw new ApiException('Erreur lors de la confirmation de la livraison', 500);
            }

            return ApiResponse::success([
                'message' => 'Livraison confirmée',
                'commission_transporteur' => $commission,
            ]);
        }

        if ($decision === 'refuser') {
            DB::table('suivi_colis')->where('id', $id)->delete();

            return ApiResponse::success(['message' => 'Demande de livraison refusée']);
        }

        throw new ApiException('Décision invalide (confirmer|refuser)');
    }

    /** DELETE /api/admin/suivi/{id} */
    public function suiviDelete(int $id): JsonResponse
    {
        if (DB::table('suivi_colis')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Étape introuvable');
        }

        return ApiResponse::success(['message' => 'Étape de suivi supprimée']);
    }

    // -----------------------------------------------------------------------
    // Voyages
    // -----------------------------------------------------------------------

    /** GET /api/admin/voyages */
    public function voyages(Request $request): JsonResponse
    {
        $statut = In::queryStr($request, 'statut');
        $search = In::queryStr($request, 'search');

        $query = DB::table('voyages as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->select('v.*', 'u.nom', 'u.prenom', 'u.email')
            ->selectSub(
                DB::table('reservations as r')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('r.voyage_id', 'v.id'),
                'nb_reservations'
            );

        if (in_array($statut, self::STATUTS_MODERATION, true)) {
            $query->where('v.statut', $statut);
        }
        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('v.pays_depart', 'like', $like)
                  ->orWhere('v.pays_destination', 'like', $like)
                  ->orWhere('u.nom', 'like', $like)
                  ->orWhere('u.prenom', 'like', $like);
            });
        }

        $voyages = $query->orderByDesc('v.date_post')->limit(200)->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($voyages);
    }

    /** POST /api/admin/voyages/{id}/statut */
    public function voyageStatut(Request $request, int $id): JsonResponse
    {
        $statut = In::str($request, 'statut');
        if (! in_array($statut, self::STATUTS_MODERATION, true)) {
            throw new ApiException('Statut invalide');
        }

        $changed = DB::table('voyages')->where('id', $id)->update(['statut' => $statut]);
        if ($changed === 0) {
            throw ApiException::notFound('Voyage introuvable');
        }

        return ApiResponse::success(['message' => "Statut du voyage mis à jour ($statut)"]);
    }

    /** DELETE /api/admin/voyages/{id} */
    public function voyageDelete(int $id): JsonResponse
    {
        if (DB::table('voyages')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Voyage introuvable');
        }

        return ApiResponse::success(['message' => 'Voyage supprimé']);
    }

    // -----------------------------------------------------------------------
    // Transporteurs
    // -----------------------------------------------------------------------

    /** GET /api/admin/transporteurs */
    public function transporteurs(Request $request): JsonResponse
    {
        $search = In::queryStr($request, 'search');

        $query = DB::table('transporteurs as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->select('t.*', 'u.nom', 'u.prenom', 'u.email', 'u.telephone')
            ->selectSub(
                DB::table('reservations as r')
                    ->join('voyages as v', 'v.id', '=', 'r.voyage_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('v.user_id', 't.user_id')
                    ->where('r.statut', 'accepte'),
                'nb_colis_transportes'
            );

        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('u.nom', 'like', $like)
                  ->orWhere('u.prenom', 'like', $like)
                  ->orWhere('u.email', 'like', $like)
                  ->orWhere('t.compagnie', 'like', $like)
                  ->orWhere('t.ville', 'like', $like)
                  ->orWhere('t.pays', 'like', $like);
            });
        }

        $transporteurs = $query->orderByDesc('t.date_creation')->limit(200)->get()
            ->map(function ($t) {
                $t = (array) $t;
                $t['photo_vehicule_url'] = Files::url($t['photo_vehicule'] ?? null);
                unset($t['photo_vehicule']);

                return $t;
            })
            ->all();

        return ApiResponse::success($transporteurs);
    }

    /** DELETE /api/admin/transporteurs/{id} — id de la FICHE (transporteurs.id). */
    public function transporteurDelete(int $id): JsonResponse
    {
        if (DB::table('transporteurs')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Transporteur introuvable');
        }

        return ApiResponse::success(['message' => 'Fiche transporteur supprimée']);
    }

    // -----------------------------------------------------------------------
    // Paiements
    // -----------------------------------------------------------------------

    /** GET /api/admin/paiements */
    public function paiements(Request $request): JsonResponse
    {
        $statut = In::queryStr($request, 'statut');
        $search = In::queryStr($request, 'search');

        $query = DB::table('paiements as p')
            ->leftJoin('colis as c', 'p.colis_id', '=', 'c.id')
            ->leftJoin('users as u', 'p.user_id', '=', 'u.id')
            ->select('p.*', 'c.nom_colis', 'c.numero_suivi', 'u.nom', 'u.prenom', 'u.email');

        if (in_array($statut, self::STATUTS_PAIEMENT, true)) {
            $query->where('p.statut', $statut);
        }
        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('p.reference', 'like', $like)
                  ->orWhere('p.numero_transaction', 'like', $like)
                  ->orWhere('c.nom_colis', 'like', $like)
                  ->orWhere('u.nom', 'like', $like);
            });
        }

        $paiements = $query->orderByDesc('p.date_creation')->limit(200)->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($paiements);
    }

    /** POST /api/admin/paiements/{id}/statut */
    public function paiementStatut(Request $request, int $id): JsonResponse
    {
        $statut = In::str($request, 'statut');
        if (! in_array($statut, self::STATUTS_PAIEMENT, true)) {
            throw new ApiException('Statut invalide');
        }

        $update = ['statut' => $statut];
        if ($statut === 'paye') {
            $update['date_paiement'] = now()->toDateTimeString();
        }

        $changed = DB::table('paiements')->where('id', $id)->update($update);
        if ($changed === 0) {
            throw ApiException::notFound('Paiement introuvable');
        }

        return ApiResponse::success(['message' => "Statut du paiement mis à jour ($statut)"]);
    }

    // -----------------------------------------------------------------------
    // Avis
    // -----------------------------------------------------------------------

    /** GET /api/admin/avis */
    public function avis(Request $request): JsonResponse
    {
        $statut = In::queryStr($request, 'statut');

        $query = DB::table('avis as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('users as t', 't.id', '=', 'a.transporteur_id')
            ->select(
                'a.*',
                'u.nom as user_nom', 'u.prenom as user_prenom',
                't.nom as transporteur_nom', 't.prenom as transporteur_prenom'
            );

        if (in_array($statut, self::STATUTS_MODERATION, true)) {
            $query->where('a.statut', $statut);
        }

        $avis = $query->orderByDesc('a.date_avis')->limit(200)->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($avis);
    }

    /** POST /api/admin/avis/{id}/statut */
    public function avisStatut(Request $request, int $id): JsonResponse
    {
        $statut = In::str($request, 'statut');
        if (! in_array($statut, self::STATUTS_MODERATION, true)) {
            throw new ApiException('Statut invalide');
        }

        $changed = DB::table('avis')->where('id', $id)->update(['statut' => $statut]);
        if ($changed === 0) {
            throw ApiException::notFound('Avis introuvable');
        }

        return ApiResponse::success(['message' => "Statut de l'avis mis à jour ($statut)"]);
    }

    /** DELETE /api/admin/avis/{id} */
    public function avisDelete(int $id): JsonResponse
    {
        if (DB::table('avis')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Avis introuvable');
        }

        return ApiResponse::success(['message' => 'Avis supprimé']);
    }

    // -----------------------------------------------------------------------
    // Messages de contact
    // -----------------------------------------------------------------------

    /**
     * GET /api/admin/contact-messages
     *
     * Effet de bord historique conservé : la consultation marque tous les
     * messages comme lus par l'administration.
     */
    public function contactMessages(Request $request): JsonResponse
    {
        $repondu = In::queryStr($request, 'repondu');

        $query = DB::table('messages_contact');
        if ($repondu === 'oui') {
            $query->whereNotNull('reponse');
        }
        if ($repondu === 'non') {
            $query->whereNull('reponse');
        }

        $messages = $query->orderByDesc('date_envoi')->limit(200)->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        DB::table('messages_contact')->where('lu_par_admin', 0)->update(['lu_par_admin' => 1]);

        return ApiResponse::success($messages);
    }

    /** POST /api/admin/contact-messages/{id}/repondre */
    public function contactRepondre(Request $request, int $id): JsonResponse
    {
        $reponse = In::str($request, 'reponse');
        if ($reponse === '' || mb_strlen($reponse) > 5000) {
            throw new ApiException('Réponse invalide');
        }

        // lu_par_utilisateur remis à 0 : la réponse doit apparaître comme non
        // lue côté utilisateur.
        $changed = DB::table('messages_contact')->where('id', $id)->update([
            'reponse' => $reponse,
            'date_reponse' => now()->toDateTimeString(),
            'lu_par_utilisateur' => 0,
        ]);
        if ($changed === 0) {
            throw ApiException::notFound('Message introuvable');
        }

        return ApiResponse::success(['message' => 'Réponse envoyée']);
    }

    /** DELETE /api/admin/contact-messages/{id} */
    public function contactDelete(int $id): JsonResponse
    {
        if (DB::table('messages_contact')->where('id', $id)->delete() === 0) {
            throw ApiException::notFound('Message introuvable');
        }

        return ApiResponse::success(['message' => 'Message supprimé']);
    }
}
