<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Paiement;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\Pricing;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Colis : liste personnelle, colis disponibles (transporteurs), détail,
 * création (avec paiement initial), modification, voyages compatibles.
 *
 * Port fidèle du contrôleur `colis.php` de l'API d'origine.
 */
class ColisController extends Controller
{
    /** Types de produits acceptés (identique à COLIS_TYPES_VALIDES). */
    private const TYPES_VALIDES = ['alimentaire', 'electronique', 'vetements', 'documents', 'autre'];

    /** GET /api/colis/mine */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $colis = DB::table('colis as c')
            ->select('c.*')
            ->selectSub(
                DB::table('reservations')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reservations.colis_id', 'c.id')
                    ->where('reservations.statut', 'accepte'),
                'nb_reservations'
            )
            ->where('c.user_id', $user->id)
            ->orderByDesc('c.date_post')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        foreach ($colis as &$c) {
            // Dernière étape de suivi.
            $last = DB::table('suivi_colis')
                ->select('statut', 'confirme_par_admin', 'demande_livraison')
                ->where('colis_id', $c['id'])
                ->orderByDesc('date_etape')
                ->first();

            $c['statut_livraison'] = $last->statut ?? null;
            $c['suivi_confirme_par_admin'] = (bool) ($last->confirme_par_admin ?? false);
            $c['demande_livraison'] = (bool) ($last->demande_livraison ?? false);

            // Réservations acceptées (transporteurs affectés).
            $c['reservations'] = [];
            if ((int) $c['nb_reservations'] > 0) {
                $reservations = DB::table('reservations as r')
                    ->join('voyages as v', 'r.voyage_id', '=', 'v.id')
                    ->join('users as u', 'v.user_id', '=', 'u.id')
                    ->select(
                        'r.id', 'r.voyage_id',
                        'u.id as transporteur_id', 'u.nom', 'u.prenom',
                        'u.telephone', 'u.email', 'u.photo_profil'
                    )
                    ->where('r.colis_id', $c['id'])
                    ->where('r.statut', 'accepte')
                    ->get();

                $c['reservations'] = $reservations->map(function ($res) {
                    $res = (array) $res;
                    $res['photo_url'] = Files::url($res['photo_profil']);
                    unset($res['photo_profil']);

                    $stats = DB::table('avis')
                        ->selectRaw('AVG(note) AS note_moyenne, COUNT(*) AS nb_avis')
                        ->where('transporteur_id', $res['transporteur_id'])
                        ->where('statut', 'approuve')
                        ->first();

                    $res['note_moyenne'] = $stats && $stats->note_moyenne !== null
                        ? round((float) $stats->note_moyenne, 2)
                        : null;
                    $res['nb_avis'] = (int) ($stats->nb_avis ?? 0);

                    return $res;
                })->all();
            }

            $c = $this->colisPublic($c);
        }
        unset($c);

        return ApiResponse::success($colis);
    }

    /** GET /api/colis/available — colis approuvés visibles par les transporteurs. */
    public function available(Request $request): JsonResponse
    {
        $search = In::queryStr($request, 'search');
        $pays = In::queryStr($request, 'pays');
        $ville = In::queryStr($request, 'ville');
        $type = In::queryStr($request, 'type');

        $query = DB::table('colis as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.statut', Colis::STATUT_APPROUVE);

        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('c.nom_colis', 'like', $like)
                  ->orWhere('c.type_produit', 'like', $like)
                  ->orWhere('c.pays', 'like', $like)
                  ->orWhere('c.ville', 'like', $like);
            });
        }
        if ($pays !== '') {
            $query->where('c.pays', $pays);
        }
        if ($ville !== '') {
            $query->where('c.ville', $ville);
        }
        if ($type !== '' && in_array($type, self::TYPES_VALIDES, true)) {
            $query->where('c.type_produit', $type);
        }

        $colis = $query->select('c.*', 'u.nom', 'u.prenom')
            ->orderByDesc('c.date_post')
            ->limit(100)
            ->get();

        $data = $colis->map(function ($row) {
            $c = (array) $row;
            // Identité du propriétaire réduite au prénom+nom : email et
            // téléphone ne sont dévoilés qu'après acceptation d'une réservation.
            $c['proprietaire'] = $c['prenom'] . ' ' . $c['nom'];
            unset($c['nom'], $c['prenom']);

            return $this->colisPublic($c);
        })->all();

        return ApiResponse::success($data);
    }

    /** GET /api/colis/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $c = DB::table('colis as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->select(
                'c.*', 'u.nom', 'u.prenom',
                'u.email as owner_email', 'u.telephone as owner_tel',
                'u.photo_profil as owner_photo'
            )
            ->where('c.id', $id)
            ->first();

        if (! $c) {
            throw ApiException::notFound('Colis introuvable');
        }
        $c = (array) $c;

        $isOwner = (int) $c['user_id'] === (int) $user->id;
        $isAdmin = $user->isAdmin();
        if (! $isOwner && ! $isAdmin && $c['statut'] !== Colis::STATUT_APPROUVE) {
            throw ApiException::forbidden('Accès refusé');
        }

        $c['owner_photo_url'] = Files::url($c['owner_photo']);
        unset($c['owner_photo']);
        if (! $isOwner && ! $isAdmin) {
            unset($c['owner_email'], $c['owner_tel']);
        }

        // Étapes de suivi visibles (un « Livré » non confirmé reste masqué).
        $c['etapes_suivi'] = DB::table('suivi_colis')
            ->select('statut', 'date_etape')
            ->where('colis_id', $id)
            ->where(function ($q) {
                $q->where('confirme_par_admin', true)
                  ->orWhere('statut', '!=', 'Livré');
            })
            ->orderBy('date_etape')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($this->colisPublic($c));
    }

    /** POST /api/colis — création + paiement initial en attente (atomique). */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $nomColis = In::str($request, 'nom_colis');
        $typeProduit = In::str($request, 'type_produit');
        $nombre = In::int($request, 'nombre_produits');
        $poids = In::float($request, 'poids');
        $dimensions = In::str($request, 'dimensions');
        $pays = In::str($request, 'pays');
        $ville = In::str($request, 'ville');
        $dateLimite = In::str($request, 'date_limite');
        $adresseDepart = In::str($request, 'adresse_depart');
        $adresseDestination = In::str($request, 'adresse_destination');

        $erreurs = [];
        if ($nomColis === '' || mb_strlen($nomColis) > 100) {
            $erreurs[] = 'nom du colis';
        }
        if (! in_array($typeProduit, self::TYPES_VALIDES, true)) {
            $erreurs[] = 'type de produit';
        }
        if ($nombre < 1 || $nombre > 10000) {
            $erreurs[] = 'nombre de produits';
        }
        if ($poids <= 0 || $poids > 1000) {
            $erreurs[] = 'poids';
        }
        if ($pays === '' || $ville === '') {
            $erreurs[] = 'destination';
        }
        if ($adresseDepart === '' || $adresseDestination === '') {
            $erreurs[] = 'adresses';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLimite) || strtotime($dateLimite) < strtotime('today')) {
            $erreurs[] = 'date limite';
        }
        if ($erreurs) {
            throw new ApiException('Champs invalides : ' . implode(', ', $erreurs));
        }

        $image = Uploads::storeImage($request->file('image_colis'), 'colis', 'colis');
        $prixFinal = Pricing::forWeight($poids);

        // Numéro de suivi unique (10 hex aléatoires, préfixe COLIS).
        do {
            $numeroSuivi = 'COLIS' . strtoupper(bin2hex(random_bytes(5)));
        } while (Colis::where('numero_suivi', $numeroSuivi)->exists());

        try {
            [$colisId, $paiementId, $reference] = DB::transaction(function () use (
                $user, $nomColis, $image, $typeProduit, $nombre, $poids, $dimensions,
                $pays, $ville, $dateLimite, $adresseDepart, $adresseDestination,
                $prixFinal, $numeroSuivi
            ) {
                $colis = Colis::create([
                    'user_id' => $user->id,
                    'nom_colis' => $nomColis,
                    'image_colis' => $image,
                    'type_produit' => $typeProduit,
                    'nombre_produits' => $nombre,
                    'poids' => $poids,
                    'dimensions' => $dimensions,
                    'pays' => $pays,
                    'ville' => $ville,
                    'date_limite' => $dateLimite,
                    'adresse_depart' => $adresseDepart,
                    'adresse_destination' => $adresseDestination,
                    'prix_estime' => $prixFinal,
                    'numero_suivi' => $numeroSuivi,
                    'statut' => Colis::STATUT_EN_ATTENTE,
                ]);

                $reference = 'PAY' . strtoupper(bin2hex(random_bytes(6)));
                $paiement = Paiement::create([
                    'user_id' => $user->id,
                    'colis_id' => $colis->id,
                    'montant' => $prixFinal,
                    'reference' => $reference,
                    'statut' => Paiement::STATUT_EN_ATTENTE,
                ]);

                return [$colis->id, $paiement->id, $reference];
            });
        } catch (\Throwable $e) {
            Log::error('Erreur création colis : ' . $e->getMessage());
            throw new ApiException('Une erreur est survenue lors de la création du colis', 500);
        }

        return ApiResponse::success([
            'colis_id' => $colisId,
            'numero_suivi' => $numeroSuivi,
            'paiement' => [
                'id' => $paiementId,
                'reference' => $reference,
                'montant' => $prixFinal,
            ],
        ], 201);
    }

    /**
     * POST /api/colis/{id} — modification par le propriétaire uniquement.
     *
     * Un admin ne peut PAS modifier le colis d'autrui via cet endpoint
     * (comportement historique : 404, la modération passe par /api/admin/*).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $colis = Colis::where('id', $id)->where('user_id', $user->id)->first();
        if (! $colis) {
            throw ApiException::notFound('Colis introuvable ou modification non autorisée');
        }

        $nomColis = In::str($request, 'nom_colis', (string) $colis->nom_colis);
        $typeProduit = In::str($request, 'type_produit', (string) $colis->type_produit);
        $nombre = In::int($request, 'nombre_produits', (int) $colis->nombre_produits);
        $poids = In::float($request, 'poids', (float) $colis->poids);
        $dimensions = In::str($request, 'dimensions', (string) $colis->dimensions);
        $pays = In::str($request, 'pays', (string) $colis->pays);
        $ville = In::str($request, 'ville', (string) $colis->ville);
        // Le cast 'date' donnerait "2027-01-01 00:00:00" ; la valeur par défaut
        // doit rester au format Y-m-d attendu par la validation.
        $dateLimite = In::str($request, 'date_limite', $colis->date_limite?->toDateString() ?? '');
        $adresseDepart = In::str($request, 'adresse_depart', (string) $colis->adresse_depart);
        $adresseDestination = In::str($request, 'adresse_destination', (string) $colis->adresse_destination);

        if ($nomColis === '' || ! in_array($typeProduit, self::TYPES_VALIDES, true)
            || $nombre < 1 || $poids <= 0 || $poids > 1000
            || $pays === '' || $ville === ''
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLimite)) {
            throw new ApiException('Données invalides');
        }

        $image = $colis->image_colis;
        $nouvelle = Uploads::storeImage($request->file('image_colis'), 'colis', 'colis');
        if ($nouvelle) {
            $image = $nouvelle;
            // Supprimer l'ancienne image si elle était bien dans nos uploads.
            if ($colis->image_colis && Str::startsWith($colis->image_colis, 'uploads/')) {
                $disk = \Illuminate\Support\Facades\Storage::disk('transport');
                if ($disk->exists($colis->image_colis)) {
                    $disk->delete($colis->image_colis);
                }
            }
        }

        // Prix recalculé côté serveur, jamais fourni par le client.
        $prix = Pricing::forWeight($poids);

        $colis->fill([
            'nom_colis' => $nomColis,
            'image_colis' => $image,
            'type_produit' => $typeProduit,
            'nombre_produits' => $nombre,
            'poids' => $poids,
            'dimensions' => $dimensions,
            'pays' => $pays,
            'ville' => $ville,
            'date_limite' => $dateLimite,
            'adresse_depart' => $adresseDepart,
            'adresse_destination' => $adresseDestination,
            'prix_estime' => $prix,
        ])->save();

        // Répercuter le nouveau prix sur un paiement encore en attente.
        Paiement::where('colis_id', $colis->id)
            ->where('statut', Paiement::STATUT_EN_ATTENTE)
            ->update(['montant' => $prix]);

        return ApiResponse::success(['message' => 'Colis mis à jour', 'prix_estime' => $prix]);
    }

    /**
     * GET /api/colis/{id}/reservations — liste des réservations d'un colis
     * (propriétaire ou admin).
     */
    public function reservations(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $colis = Colis::find($id);
        if (! $colis) {
            throw ApiException::notFound('Colis introuvable');
        }
        if ((int) $colis->user_id !== (int) $user->id && ! $user->isAdmin()) {
            throw ApiException::forbidden('Accès refusé');
        }

        $reservations = DB::table('reservations as r')
            ->join('voyages as v', 'r.voyage_id', '=', 'v.id')
            ->join('users as u', 'v.user_id', '=', 'u.id')
            ->select(
                'r.*',
                'v.pays_depart', 'v.pays_destination', 'v.date_depart', 'v.heure_depart',
                'u.id as transporteur_id', 'u.nom', 'u.prenom', 'u.email', 'u.telephone'
            )
            ->where('r.colis_id', $id)
            ->orderByDesc('r.date_reservation')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ApiResponse::success($reservations);
    }

    /** GET /api/colis/{id}/voyages-compatibles */
    public function voyagesCompatibles(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $colis = Colis::find($id);
        if (! $colis) {
            throw ApiException::notFound('Colis introuvable');
        }
        if ((int) $colis->user_id !== (int) $user->id && ! $user->isAdmin()) {
            throw ApiException::forbidden('Accès refusé');
        }

        $voyages = DB::table('voyages as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->select('v.*', 'u.nom', 'u.prenom', 'u.photo_profil', 'u.id as transporteur_id')
            ->where('v.pays_destination', $colis->pays)
            ->where('v.poids_max', '>=', $colis->poids)
            ->whereDate('v.date_depart', '>=', now()->toDateString())
            ->where('v.statut', 'approuve')
            ->orderBy('v.date_depart')
            ->get()
            ->map(function ($row) {
                $v = (array) $row;
                $v['photo_url'] = Files::url($v['photo_profil']);
                unset($v['photo_profil']);

                return $v;
            })
            ->all();

        return ApiResponse::success([
            // getAttributes() : valeurs brutes telles que lues de la base,
            // comme le SELECT * de l'API d'origine (dates au format MySQL).
            'colis' => $this->colisPublic($colis->getAttributes()),
            'voyages' => $voyages,
        ]);
    }

    /**
     * Enrichit une ligne `colis` : `image_url` remplace `image_colis`
     * (équivalent de colis_public()).
     */
    private function colisPublic(array $c): array
    {
        $c['image_url'] = Files::url($c['image_colis'] ?? null);
        unset($c['image_colis']);

        return $c;
    }
}
