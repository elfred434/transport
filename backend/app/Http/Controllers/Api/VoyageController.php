<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Reservation;
use App\Models\SuiviColis;
use App\Models\Transporteur;
use App\Models\Voyage;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Voyages & réservations : proposer un voyage (devenir transporteur),
 * voyages disponibles, réservations (création, réception, décision).
 *
 * Port fidèle du contrôleur `voyages.php` de l'API d'origine.
 */
class VoyageController extends Controller
{
    /**
     * POST /api/voyages — proposition de voyage.
     *
     * Effet de bord historique conservé : la première proposition fait
     * atomiquement passer l'utilisateur au rôle `transporteur` (sans jamais
     * écraser un rôle admin) et crée sa fiche transporteur si absente.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $numeroPermis = In::str($request, 'numero_permis');
        $vehicule = In::str($request, 'vehicule');
        $compagnie = In::str($request, 'compagnie');
        $adresse = In::str($request, 'adresse');
        $ville = In::str($request, 'ville');
        $pays = In::str($request, 'pays');
        $paysDepart = In::str($request, 'pays_depart');
        $paysDestination = In::str($request, 'pays_destination');
        $dateDepart = In::str($request, 'date_depart');
        $heureDepart = In::str($request, 'heure_depart');
        $poidsMax = In::float($request, 'poids_max');
        $email = In::str($request, 'email');
        $telephone = In::str($request, 'telephone');

        $erreurs = [];
        if ($numeroPermis === '') {
            $erreurs[] = 'numéro de permis';
        }
        if ($vehicule === '') {
            $erreurs[] = 'véhicule';
        }
        if ($paysDepart === '') {
            $erreurs[] = 'pays de départ';
        }
        if ($paysDestination === '') {
            $erreurs[] = 'pays de destination';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDepart) || strtotime($dateDepart) < strtotime('today')) {
            $erreurs[] = 'date de départ';
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $heureDepart)) {
            $erreurs[] = 'heure de départ';
        }
        if ($poidsMax <= 0 || $poidsMax > 100000) {
            $erreurs[] = 'poids maximum';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'email';
        }
        if ($erreurs) {
            throw new ApiException('Champs invalides : ' . implode(', ', $erreurs));
        }

        $photo = Uploads::storeImage($request->file('photo_vehicule'), 'vehicules', 'vehicule');

        try {
            $voyageId = DB::transaction(function () use (
                $user, $paysDepart, $paysDestination, $dateDepart, $heureDepart,
                $poidsMax, $email, $telephone,
                $numeroPermis, $vehicule, $compagnie, $adresse, $ville, $pays, $photo
            ) {
                $voyage = Voyage::create([
                    'user_id' => $user->id,
                    'pays_depart' => $paysDepart,
                    'pays_destination' => $paysDestination,
                    'date_depart' => $dateDepart,
                    'heure_depart' => $heureDepart,
                    'poids_max' => $poidsMax,
                    'email' => $email,
                    'telephone' => $telephone,
                    // statut : défaut MySQL 'en_attente'
                ]);

                // Passer transporteur sans jamais écraser le rôle admin.
                DB::table('users')
                    ->where('id', $user->id)
                    ->where('role', 'utilisateur')
                    ->update(['role' => 'transporteur']);

                if (! Transporteur::where('user_id', $user->id)->exists()) {
                    Transporteur::create([
                        'user_id' => $user->id,
                        'numero_permis' => $numeroPermis,
                        'vehicule' => $vehicule,
                        'compagnie' => $compagnie,
                        'adresse' => $adresse,
                        'ville' => $ville,
                        'pays' => $pays,
                        'photo_vehicule' => $photo,
                    ]);
                }

                return $voyage->id;
            });
        } catch (\Throwable $e) {
            Log::error('Erreur voyage_create : ' . $e->getMessage());
            throw new ApiException('Une erreur est survenue lors de la proposition de voyage', 500);
        }

        return ApiResponse::success([
            'message' => 'Votre voyage a été proposé. Il sera visible après validation par un administrateur.',
            'voyage_id' => $voyageId,
        ], 201);
    }

    /** GET /api/voyages/available — voyages approuvés à venir. */
    public function available(Request $request): JsonResponse
    {
        $search = In::queryStr($request, 'search');
        $paysDepart = In::queryStr($request, 'pays_depart');
        $paysDestination = In::queryStr($request, 'pays_destination');

        $query = DB::table('voyages as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.statut', Voyage::STATUT_APPROUVE)
            ->whereDate('v.date_depart', '>=', now()->toDateString());

        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('v.pays_depart', 'like', $like)
                  ->orWhere('v.pays_destination', 'like', $like);
            });
        }
        if ($paysDepart !== '') {
            $query->where('v.pays_depart', $paysDepart);
        }
        if ($paysDestination !== '') {
            $query->where('v.pays_destination', $paysDestination);
        }

        $voyages = $query->select('v.*', 'u.nom', 'u.prenom', 'u.id as transporteur_id', 'u.photo_profil')
            ->orderBy('v.date_depart')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $v = (array) $row;
                $v['photo_url'] = Files::url($v['photo_profil']);
                // Coordonnées du transporteur non publiques à ce stade.
                unset($v['photo_profil'], $v['email'], $v['telephone']);

                return $v;
            })
            ->all();

        return ApiResponse::success($voyages);
    }

    /** GET /api/voyages/mine */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $voyages = DB::table('voyages as v')
            ->select('v.*')
            ->selectSub(
                DB::table('reservations')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reservations.voyage_id', 'v.id'),
                'nb_reservations'
            )
            ->where('v.user_id', $user->id)
            ->orderByDesc('v.date_post')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($voyages);
    }

    /** POST /api/reservations — un transporteur réserve un colis pour SON voyage. */
    public function reserver(Request $request): JsonResponse
    {
        $user = $request->user();

        $colisId = In::int($request, 'colis_id');
        $voyageId = In::int($request, 'voyage_id');
        if ($colisId <= 0 || $voyageId <= 0) {
            throw new ApiException('colis_id et voyage_id sont obligatoires');
        }

        if (! Voyage::where('id', $voyageId)->where('user_id', $user->id)->exists()) {
            throw ApiException::forbidden('Ce voyage ne vous appartient pas');
        }

        if (! Colis::where('id', $colisId)->where('statut', Colis::STATUT_APPROUVE)->exists()) {
            throw ApiException::notFound('Colis introuvable ou non approuvé');
        }

        $existe = Reservation::where('colis_id', $colisId)->where('voyage_id', $voyageId)->exists();
        if ($existe) {
            throw ApiException::conflict('Vous avez déjà réservé ce colis pour ce voyage');
        }

        Reservation::create([
            'colis_id' => $colisId,
            'voyage_id' => $voyageId,
            'statut' => Reservation::STATUT_EN_ATTENTE,
        ]);

        return ApiResponse::success(
            ['message' => 'Votre réservation a été envoyée au propriétaire du colis'],
            201
        );
    }

    /** GET /api/reservations/recues — réservations reçues par le transporteur connecté. */
    public function recues(Request $request): JsonResponse
    {
        $user = $request->user();

        $reservations = DB::table('reservations as r')
            ->join('voyages as v', 'v.id', '=', 'r.voyage_id')
            ->join('colis as c', 'c.id', '=', 'r.colis_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'c.user_id')
            ->select(
                'r.*',
                'c.nom_colis', 'c.pays', 'c.ville', 'c.poids', 'c.image_colis',
                'c.numero_suivi', 'c.prix_estime', 'c.user_id as client_id',
                'v.pays_depart', 'v.pays_destination', 'v.date_depart', 'v.heure_depart',
                'cu.nom as client_nom', 'cu.prenom as client_prenom', 'cu.telephone as client_tel'
            )
            ->where('v.user_id', $user->id)
            ->orderByDesc('r.date_reservation')
            ->get();

        $data = $reservations->map(function ($row) {
            $r = (array) $row;
            $r['statut_suivi'] = DB::table('suivi_colis')
                ->where('colis_id', $r['colis_id'])
                ->orderByDesc('date_etape')
                ->value('statut') ?: 'En attente';
            $r['image_url'] = Files::url($r['image_colis']);
            unset($r['image_colis']);

            return $r;
        })->all();

        return ApiResponse::success($data);
    }

    /**
     * POST /api/reservations/{id}/action — le propriétaire du colis (ou un
     * admin, pour la modération) accepte / refuse / annule une réservation.
     *
     * `annule` remet la réservation en attente et purge le suivi du colis :
     * le cycle de livraison repart de zéro si un autre transporteur est choisi.
     */
    public function action(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $action = In::str($request, 'action');

        if (! in_array($action, ['accepte', 'refuse', 'annule'], true)) {
            throw new ApiException('Action invalide');
        }

        $reservation = DB::table('reservations as r')
            ->join('colis as c', 'c.id', '=', 'r.colis_id')
            ->select('r.*', 'c.user_id as colis_owner')
            ->where('r.id', $id)
            ->first();

        if (! $reservation) {
            throw ApiException::notFound('Réservation introuvable');
        }
        if ((int) $reservation->colis_owner !== (int) $user->id && ! $user->isAdmin()) {
            throw ApiException::forbidden('Vous n\'êtes pas autorisé à modifier cette réservation');
        }

        $nouveauStatut = ($action === 'annule') ? Reservation::STATUT_EN_ATTENTE : $action;

        Reservation::where('id', $id)->update(['statut' => $nouveauStatut]);

        if ($action === 'annule') {
            SuiviColis::where('colis_id', $reservation->colis_id)->delete();
        }

        return ApiResponse::success(['message' => 'Réservation mise à jour', 'statut' => $nouveauStatut]);
    }
}
