<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\KkiapayService;
use App\Support\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    private const STATUTS_MODERATION = ['en_attente', 'approuve', 'refuse'];
    private const STATUTS_PAIEMENT = ['en_attente', 'paye', 'echec', 'annule'];
    private const ROLES_ALL = ['client','utilisateur','transporteur','admin','super_admin'];
    private const ROLES_CLIENT_TRANSPORTEUR = ['client','utilisateur','transporteur'];
    private const ROLES_ADMIN_CAN_SEE = ['client','utilisateur','transporteur'];
    private const ROLES_ASSIGNABLE_ADMIN = ['client','transporteur'];
    private const ROLES_ASSIGNABLE_SUPER = ['client','transporteur','admin','super_admin'];

    /** GET /api/admin/stats */
    public function stats(Request $request): JsonResponse
    {
        // Assurer existence admin_wallet
        if (DB::table('admin_wallet')->count() === 0) {
            DB::table('admin_wallet')->insert(['id'=>1,'solde'=>0]);
        }

        $stats = [
            'nb_users' => (int) DB::table('users')->count(),
            'nb_clients' => (int) DB::table('users')->whereIn('role', ['client','utilisateur'])->count(),
            'nb_transporteurs_users' => (int) DB::table('users')->where('role','transporteur')->count(),
            'nb_admins' => (int) DB::table('users')->where('role','admin')->count(),
            'nb_super_admins' => (int) DB::table('users')->where('role','super_admin')->count(),
            'nb_colis' => (int) DB::table('colis')->count(),
            'nb_voyages' => (int) DB::table('voyages')->count(),
            'nb_transporteurs' => (int) DB::table('transporteurs')->count(),
            'nb_paiements' => (int) DB::table('paiements')->count(),
            'nb_avis' => (int) DB::table('avis')->count(),
            'colis_en_attente' => (int) DB::table('colis')->where('statut', 'en_attente')->count(),
            'voyages_en_attente' => (int) DB::table('voyages')->where('statut', 'en_attente')->count(),
            'avis_en_attente' => (int) DB::table('avis')->where('statut', 'en_attente')->count(),
            'demandes_livraison' => (int) DB::table('suivi_colis as s')
                ->where('s.demande_livraison', 1)
                ->where('s.confirme_par_admin', 0)
                ->where('s.statut', 'Livré')
                ->whereRaw('s.date_etape = (SELECT MAX(s2.date_etape) FROM suivi_colis s2 WHERE s2.colis_id = s.colis_id)')
                ->count(),
            'montant_total_paye' => (float) DB::table('paiements')->where('statut', 'paye')->sum('montant'),
            'messages_contact_non_lus' => (int) DB::table('messages_contact')->where('lu_par_admin', 0)->count(),
            // Nouveau : wallet admin et retraits
            'admin_wallet_solde' => (float) (DB::table('admin_wallet')->where('id',1)->value('solde') ?: 0),
            'admin_commission_total' => (float) DB::table('retraits')->where('type','transporteur')->where('statut','paye')->sum(DB::raw('montant * 0.05 / 0.95')), // approx
            'retraits_en_attente' => (int) DB::table('retraits')->where('statut','en_attente')->count(),
            'retraits_payes' => (int) DB::table('retraits')->where('statut','paye')->count(),
            'total_paye_transporteurs' => (float) DB::table('retraits')->where('type','transporteur')->where('statut','paye')->sum('montant'),
            'total_frais_admin' => (float) DB::table('admin_wallet')->where('id',1)->value('solde'),
        ];

        // Calcul plus précis admin commission : somme des 5% des livraisons confirmées
        try {
            $stats['admin_commission_total'] = (float) DB::table('suivi_colis as s')
                ->join('colis as c','c.id','=','s.colis_id')
                ->where('s.confirme_par_admin',1)
                ->sum(DB::raw('c.prix_estime * 0.05'));
        } catch (\Throwable $e) {
            // fallback
        }

        // Détail des livraisons en attente (20 derniers)
        $pendingLivraisons = [];
        try {
            $pendingLivraisons = DB::table('suivi_colis as s')
                ->join('colis as c','c.id','=','s.colis_id')
                ->leftJoin('users as uc','uc.id','=','c.user_id')
                ->leftJoin('reservations as r', function($j){
                    $j->on('r.colis_id','=','c.id')->whereIn('r.statut',['accepte','termine']);
                })
                ->leftJoin('voyages as v','v.id','=','r.voyage_id')
                ->leftJoin('users as ut','ut.id','=','v.user_id')
                ->where('s.demande_livraison',1)
                ->where('s.confirme_par_admin',0)
                ->where('s.statut','Livré')
                ->whereRaw('s.date_etape = (SELECT MAX(s2.date_etape) FROM suivi_colis s2 WHERE s2.colis_id = s.colis_id)')
                ->select(
                    's.id as suivi_id','s.date_etape',
                    'c.id as colis_id','c.nom_colis','c.numero_suivi','c.prix_estime','c.ville','c.pays',
                    'uc.nom as client_nom','uc.prenom as client_prenom','uc.telephone as client_tel',
                    'ut.id as transporteur_id','ut.nom as transporteur_nom','ut.prenom as transporteur_prenom','ut.telephone as transporteur_tel'
                )
                ->orderByDesc('s.date_etape')
                ->limit(20)
                ->get()
                ->map(fn($row) => (array)$row)
                ->all();
        } catch (\Throwable $e) {
            // table peut-être absente, on ignore
        }
        $stats['pending_livraisons'] = $pendingLivraisons;

        // Compter notifs non lues si table existe
        try {
            $stats['notifications_non_lues'] = (int) DB::table('notifications_admin')->where('lu',0)->count();
        } catch (\Throwable $e) {
            $stats['notifications_non_lues'] = 0;
        }

        return ApiResponse::success($stats);
    }

    /** GET /api/admin/notifications */
    public function notifications(Request $request): JsonResponse
    {
        try {
            $nonLues = DB::table('notifications_admin')->where('lu',0)->count();
            $liste = DB::table('notifications_admin')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn($n) => (array)$n)
                ->all();
            return ApiResponse::success([
                'non_lues' => (int)$nonLues,
                'notifications' => $liste,
            ]);
        } catch (\Throwable $e) {
            // Fallback si table absente : liste basée sur pending_livraisons
            $pending = DB::table('suivi_colis as s')
                ->join('colis as c','c.id','=','s.colis_id')
                ->leftJoin('users as uc','uc.id','=','c.user_id')
                ->where('s.demande_livraison',1)
                ->where('s.confirme_par_admin',0)
                ->where('s.statut','Livré')
                ->whereRaw('s.date_etape = (SELECT MAX(s2.date_etape) FROM suivi_colis s2 WHERE s2.colis_id = s.colis_id)')
                ->select('s.id as suivi_id','s.date_etape as created_at','c.id as colis_id','c.nom_colis',
                    DB::raw("CONCAT('Nouvelle livraison à confirmer : ', c.nom_colis) as message"))
                ->orderByDesc('s.date_etape')->limit(50)->get()
                ->map(fn($n) => (array)$n)->all();
            return ApiResponse::success([
                'non_lues' => count($pending),
                'notifications' => $pending,
                'fallback' => true,
            ]);
        }
    }

    /** POST /api/admin/notifications/{id}/read */
    public function notificationRead(int $id): JsonResponse
    {
        try {
            DB::table('notifications_admin')->where('id',$id)->update(['lu'=>1,'updated_at'=>now()]);
        } catch (\Throwable $e) {
            // table absente : silencieux
        }
        return ApiResponse::success(['message'=>'Notification marquée comme lue']);
    }

    /** POST /api/admin/notifications/read-all */
    public function notificationsReadAll(): JsonResponse
    {
        try {
            DB::table('notifications_admin')->where('lu',0)->update(['lu'=>1,'updated_at'=>now()]);
        } catch (\Throwable $e) {
            // silencieux
        }
        return ApiResponse::success(['message'=>'Toutes les notifications marquées comme lues']);
    }

    /** GET /api/admin/users */
    public function users(Request $request): JsonResponse
    {
        $search = In::queryStr($request, 'search');
        $role = In::queryStr($request, 'role');
        $me = $request->user();

        $query = DB::table('users')
            ->select('id', 'nom', 'prenom', 'email', 'telephone', 'role', 'photo_profil', 'date_inscription');

        // Admin simple ne voit que clients et transporteurs
        if ($me->isAdminSimple()) {
            $query->whereIn('role', self::ROLES_ADMIN_CAN_SEE);
        }

        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) {
                $q->where('nom', 'like', $like)
                  ->orWhere('prenom', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }
        if (in_array($role, self::ROLES_ALL, true)) {
            // Admin simple ne peut filtrer que sur ce qu'il peut voir
            if ($me->isAdminSimple() && !in_array($role, self::ROLES_ADMIN_CAN_SEE, true)) {
                throw ApiException::forbidden('Vous ne pouvez pas lister ce rôle');
            }
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('date_inscription')->limit(200)->get()
            ->map(function ($u) {
                $u = (array) $u;
                $u['photo_url'] = Files::url($u['photo_profil']);
                unset($u['photo_profil']);
                // Normaliser utilisateur -> client pour frontend
                if ($u['role'] === 'utilisateur') $u['role'] = 'client';
                return $u;
            })->all();

        return ApiResponse::success($users);
    }

    /** POST /api/admin/users */
    public function userCreate(Request $request): JsonResponse
    {
        $me = $request->user();
        $nom = In::str($request, 'nom');
        $prenom = In::str($request, 'prenom');
        $email = strtolower(trim(In::str($request, 'email')));
        $password = In::str($request, 'password');
        $role = In::str($request, 'role');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiException('Adresse email invalide');
        if (strlen($password) < 8) throw new ApiException('Le mot de passe doit contenir au moins 8 caractères');
        if ($nom === '' || $prenom === '') throw new ApiException('Le nom et le prénom sont obligatoires');
        if (!in_array($role, self::ROLES_ALL, true)) throw new ApiException('Rôle invalide');
        // Normaliser
        if ($role === 'utilisateur') $role = 'client';
        if (User::where('email', $email)->exists()) throw ApiException::conflict('Cet email est déjà utilisé');

        // Permissions
        if ($me->isAdminSimple()) {
            if (!in_array($role, self::ROLES_ASSIGNABLE_ADMIN, true)) {
                throw ApiException::forbidden('Un admin simple ne peut créer que des clients ou transporteurs');
            }
        } elseif ($me->isSuperAdmin()) {
            if (!in_array($role, self::ROLES_ASSIGNABLE_SUPER, true)) throw new ApiException('Rôle invalide');
        } else {
            throw ApiException::forbidden('Non autorisé');
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

    /** POST /api/admin/users/{id}/role */
    public function userUpdateRole(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        $role = In::str($request, 'role');
        if ($role === 'utilisateur') $role = 'client';
        if (!in_array($role, self::ROLES_ALL, true)) throw new ApiException('Rôle invalide');

        $target = User::find($id);
        if (!$target) throw ApiException::notFound('Utilisateur introuvable');
        if ($id === (int) $me->id) throw new ApiException('Impossible de modifier votre propre rôle');

        // Admin simple restrictions
        if ($me->isAdminSimple()) {
            // Ne peut pas toucher aux admins/super_admins
            if (in_array($target->role, ['admin','super_admin'], true)) {
                throw ApiException::forbidden('Un admin simple ne peut pas modifier un admin');
            }
            if (!in_array($role, self::ROLES_ASSIGNABLE_ADMIN, true)) {
                throw ApiException::forbidden('Un admin simple ne peut assigner que client ou transporteur');
            }
        }

        // Super admin peut tout sauf se rétrograder lui-même (déjà bloqué)
        if ($me->isSuperAdmin()) {
            if (!in_array($role, self::ROLES_ASSIGNABLE_SUPER, true)) throw new ApiException('Rôle invalide');
        }

        $target->role = $role;
        $target->save();

        return ApiResponse::success(['message' => "Rôle mis à jour en $role"]);
    }

    /** DELETE /api/admin/users/{id} */
    public function userDelete(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        if ($id === (int) $me->id) throw new ApiException('Impossible de supprimer votre propre compte');

        $target = User::find($id);
        if (!$target) throw ApiException::notFound('Utilisateur introuvable');

        if ($me->isAdminSimple()) {
            if (in_array($target->role, ['admin','super_admin'], true)) {
                throw ApiException::forbidden('Un admin simple ne peut pas supprimer un administrateur');
            }
        }

        // Super admin ne peut pas supprimer le dernier super_admin
        if ($target->isSuperAdmin()) {
            $countSuper = User::where('role','super_admin')->count();
            if ($countSuper <= 1) throw new ApiException('Impossible de supprimer le dernier Super Admin');
        }

        if (User::where('id', $id)->delete() === 0) throw ApiException::notFound('Utilisateur introuvable');

        return ApiResponse::success(['message' => 'Utilisateur supprimé']);
    }

    // Colis + suivi (identique, admin simple et super_admin peuvent modérer)
    public function colis(Request $request): JsonResponse
    {
        $statut = In::queryStr($request, 'statut');
        $search = In::queryStr($request, 'search');
        $query = DB::table('colis as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->select('c.*', 'u.nom', 'u.prenom', 'u.email')
            ->selectSub(DB::table('suivi_colis as s')->select('s.id')->whereColumn('s.colis_id','c.id')->where('s.demande_livraison',1)->where('s.confirme_par_admin',0)->orderByDesc('s.date_etape')->limit(1), 'demande_livraison_id')
            ->selectSub(DB::table('suivi_colis as s2')->select('s2.statut')->whereColumn('s2.colis_id','c.id')->orderByDesc('s2.date_etape')->limit(1), 'statut_livraison');
        if (in_array($statut, self::STATUTS_MODERATION, true)) $query->where('c.statut', $statut);
        if ($search !== '') {
            $like = "%$search%";
            $query->where(function ($q) use ($like) { $q->where('c.nom_colis','like',$like)->orWhere('c.numero_suivi','like',$like)->orWhere('c.pays','like',$like)->orWhere('c.ville','like',$like); });
        }
        $colis = $query->orderByDesc('c.date_post')->limit(200)->get()->map(function ($c){ $c=(array)$c; $c['image_url']=Files::url($c['image_colis']??null); unset($c['image_colis']); return $c; })->all();
        return ApiResponse::success($colis);
    }

    public function colisStatut(Request $request, int $id): JsonResponse
    {
        $statut = In::str($request, 'statut');
        if (!in_array($statut, self::STATUTS_MODERATION, true)) throw new ApiException('Statut invalide');
        $changed = DB::table('colis')->where('id',$id)->update(['statut'=>$statut]);
        if ($changed===0) throw ApiException::notFound('Colis introuvable');
        return ApiResponse::success(['message'=>"Statut du colis mis à jour ($statut)"]);
    }

    public function colisDelete(int $id): JsonResponse
    {
        if (DB::table('colis')->where('id',$id)->delete()===0) throw ApiException::notFound('Colis introuvable');
        return ApiResponse::success(['message'=>'Colis supprimé']);
    }

    public function livraisonDecision(Request $request, int $id): JsonResponse
    {
        $decision = In::str($request, 'decision');
        $etape = DB::table('suivi_colis')->where('id',$id)->where('demande_livraison',1)->first();
        if (!$etape) throw ApiException::notFound('Demande de livraison introuvable');
        // Marquer toutes les notifs liées comme lues
        try {
            DB::table('notifications_admin')->where('colis_id',$etape->colis_id)->where('type','livraison')->update(['lu'=>1,'updated_at'=>now()]);
        } catch (\Throwable $e) { /* silencieux */ }
        if ($decision==='confirmer') {
            try {
                $result = DB::transaction(function () use ($etape,$id){
                    DB::table('suivi_colis')->where('id',$id)->update(['confirme_par_admin'=>1,'demande_livraison'=>0]);
                    DB::table('reservations')->where('colis_id',$etape->colis_id)->where('statut','accepte')->update(['statut'=>'termine']);

                    // Récupérer prix et transporteur + tel
                    $info = DB::table('colis as c')
                        ->join('reservations as r', function($join){ $join->on('r.colis_id','=','c.id')->where('r.statut','=','termine'); })
                        ->join('voyages as v','v.id','=','r.voyage_id')
                        ->join('users as u','u.id','=','v.user_id')
                        ->select('c.prix_estime','c.id as colis_id','c.nom_colis','v.user_id as transporteur_id','u.telephone','u.nom','u.prenom','u.email')
                        ->where('c.id',$etape->colis_id)
                        ->orderByDesc('r.date_reservation')
                        ->first();

                    $commissionTransporteur = 0.0;
                    $commissionAdmin = 0.0;
                    $retraitId = null;
                    $payoutResult = null;

                    if ($info){
                        $prix = (float)$info->prix_estime;
                        $commissionTransporteur = Pricing::commissionTransporteur($prix); // 95%
                        $commissionAdmin = Pricing::commissionAdmin($prix); // 5%

                        // Créditer transporteur solde (temporaire, sera débité après payout auto)
                        DB::table('transporteurs')->where('user_id',$info->transporteur_id)->increment('solde',$commissionTransporteur);

                        // Créditer admin wallet
                        DB::table('admin_wallet')->where('id',1)->increment('solde',$commissionAdmin);
                        // Si table vide (pas de ligne 1), créer
                        if (DB::table('admin_wallet')->where('id',1)->count() === 0) {
                            DB::table('admin_wallet')->insert(['id'=>1,'solde'=>$commissionAdmin]);
                        }

                        // Préparer retrait automatique pour transporteur
                        $telephone = $info->telephone ?: '';
                        $reference = 'AUTO-'.$info->colis_id.'-'.strtoupper(uniqid());

                        // Créer retrait en_attente
                        $retraitId = DB::table('retraits')->insertGetId([
                            'user_id' => $info->transporteur_id,
                            'type' => 'transporteur',
                            'montant' => $commissionTransporteur,
                            'frais' => 0,
                            'montant_net' => $commissionTransporteur,
                            'statut' => 'en_attente',
                            'methode' => 'mobile_money',
                            'numero' => $telephone,
                            'operateur' => null,
                            'reference' => $reference,
                            'details' => 'Paiement automatique livraison colis '.$info->nom_colis.' (#'.$info->colis_id.') - Prix estimé '.$prix.' XOF, commission 95% = '.$commissionTransporteur.' XOF',
                            'colis_id' => $info->colis_id,
                            'date_demande' => now(),
                            'traite_par' => null,
                        ]);

                        // Tenter payout automatique via Kkiapay si numéro présent
                        if ($telephone !== '') {
                            try {
                                $kkiapay = new KkiapayService();
                                $payoutResult = $kkiapay->payout($telephone, $commissionTransporteur, $reference);

                                $statutPayout = ($payoutResult['status'] ?? 'FAILED') === 'SUCCESS' ? 'paye' : 'echec';

                                DB::table('retraits')->where('id',$retraitId)->update([
                                    'statut' => $statutPayout,
                                    'date_traitement' => now(),
                                    'kkiapay_response' => json_encode($payoutResult),
                                ]);

                                // Si payout réussi, débiter solde transporteur (argent envoyé)
                                if ($statutPayout === 'paye') {
                                    DB::table('transporteurs')->where('user_id',$info->transporteur_id)->decrement('solde',$commissionTransporteur);
                                }

                            } catch (\Throwable $e) {
                                Log::error('Payout auto échoué: '.$e->getMessage());
                                DB::table('retraits')->where('id',$retraitId)->update([
                                    'statut' => 'echec',
                                    'kkiapay_response' => json_encode(['error'=>$e->getMessage()]),
                                ]);
                            }
                        } else {
                            // Pas de téléphone -> laisser en_attente pour traitement manuel
                            Log::warning('Payout auto impossible: téléphone manquant pour transporteur '.$info->transporteur_id);
                        }
                    }

                    return [
                        'commission_transporteur' => $commissionTransporteur,
                        'commission_admin' => $commissionAdmin,
                        'retrait_id' => $retraitId,
                        'payout' => $payoutResult,
                    ];
                });
            } catch (\Throwable $e){
                Log::error('Erreur confirmation livraison : '.$e->getMessage().' '.$e->getTraceAsString());
                throw new ApiException('Erreur lors de la confirmation de la livraison: '.$e->getMessage(),500);
            }
            return ApiResponse::success([
                'message'=>'Livraison confirmée',
                'commission_transporteur'=>$result['commission_transporteur'],
                'commission_admin'=>$result['commission_admin'],
                'commission_rate_transporteur'=>Pricing::COMMISSION_TRANSPORTEUR_RATE,
                'commission_rate_admin'=>Pricing::COMMISSION_ADMIN_RATE,
                'retrait_automatique_id'=>$result['retrait_id'],
                'payout'=>$result['payout'],
            ]);
        }
        if ($decision==='refuser'){ DB::table('suivi_colis')->where('id',$id)->delete(); return ApiResponse::success(['message'=>'Demande de livraison refusée']); }
        throw new ApiException('Décision invalide (confirmer|refuser)');
    }

    public function suiviDelete(int $id): JsonResponse
    {
        if (DB::table('suivi_colis')->where('id',$id)->delete()===0) throw ApiException::notFound('Étape introuvable');
        return ApiResponse::success(['message'=>'Étape de suivi supprimée']);
    }

    public function voyages(Request $request): JsonResponse
    {
        $statut = In::queryStr($request,'statut'); $search=In::queryStr($request,'search');
        $query = DB::table('voyages as v')->leftJoin('users as u','v.user_id','=','u.id')->select('v.*','u.nom','u.prenom','u.email')->selectSub(DB::table('reservations as r')->selectRaw('COUNT(*)')->whereColumn('r.voyage_id','v.id'),'nb_reservations');
        if (in_array($statut,self::STATUTS_MODERATION,true)) $query->where('v.statut',$statut);
        if ($search!==''){ $like="%$search%"; $query->where(function($q) use($like){ $q->where('v.pays_depart','like',$like)->orWhere('v.pays_destination','like',$like)->orWhere('u.nom','like',$like)->orWhere('u.prenom','like',$like); }); }
        $voyages=$query->orderByDesc('v.date_post')->limit(200)->get()->map(fn($row)=>(array)$row)->all();
        return ApiResponse::success($voyages);
    }

    public function voyageStatut(Request $request,int $id): JsonResponse
    {
        $statut=In::str($request,'statut'); if(!in_array($statut,self::STATUTS_MODERATION,true)) throw new ApiException('Statut invalide');
        $changed=DB::table('voyages')->where('id',$id)->update(['statut'=>$statut]); if($changed===0) throw ApiException::notFound('Voyage introuvable');
        return ApiResponse::success(['message'=>"Statut du voyage mis à jour ($statut)"]);
    }

    public function voyageDelete(int $id): JsonResponse
    {
        if(DB::table('voyages')->where('id',$id)->delete()===0) throw ApiException::notFound('Voyage introuvable');
        return ApiResponse::success(['message'=>'Voyage supprimé']);
    }

    public function transporteurs(Request $request): JsonResponse
    {
        $search=In::queryStr($request,'search');
        $query=DB::table('transporteurs as t')->join('users as u','u.id','=','t.user_id')->select('t.*','u.nom','u.prenom','u.email','u.telephone')->selectSub(DB::table('reservations as r')->join('voyages as v','v.id','=','r.voyage_id')->selectRaw('COUNT(*)')->whereColumn('v.user_id','t.user_id')->whereIn('r.statut',['accepte','termine']),'nb_colis_transportes');
        if($search!==''){ $like="%$search%"; $query->where(function($q) use($like){ $q->where('u.nom','like',$like)->orWhere('u.prenom','like',$like)->orWhere('u.email','like',$like)->orWhere('t.compagnie','like',$like)->orWhere('t.ville','like',$like)->orWhere('t.pays','like',$like); }); }
        $transporteurs=$query->orderByDesc('t.date_creation')->limit(200)->get()->map(function($t){ $t=(array)$t; $t['photo_vehicule_url']=Files::url($t['photo_vehicule']??null); unset($t['photo_vehicule']); return $t; })->all();
        return ApiResponse::success($transporteurs);
    }

    public function transporteurDelete(int $id): JsonResponse
    {
        if(DB::table('transporteurs')->where('id',$id)->delete()===0) throw ApiException::notFound('Transporteur introuvable');
        return ApiResponse::success(['message'=>'Fiche transporteur supprimée']);
    }

    public function paiements(Request $request): JsonResponse
    {
        $statut=In::queryStr($request,'statut'); $search=In::queryStr($request,'search');
        $query=DB::table('paiements as p')->leftJoin('colis as c','p.colis_id','=','c.id')->leftJoin('users as u','p.user_id','=','u.id')->select('p.*','c.nom_colis','c.numero_suivi','u.nom','u.prenom','u.email');
        if(in_array($statut,self::STATUTS_PAIEMENT,true)) $query->where('p.statut',$statut);
        if($search!==''){ $like="%$search%"; $query->where(function($q) use($like){ $q->where('p.reference','like',$like)->orWhere('p.numero_transaction','like',$like)->orWhere('c.nom_colis','like',$like)->orWhere('u.nom','like',$like); }); }
        $paiements=$query->orderByDesc('p.date_creation')->limit(200)->get()->map(fn($row)=>(array)$row)->all();
        return ApiResponse::success($paiements);
    }

    public function paiementStatut(Request $request,int $id): JsonResponse
    {
        $statut=In::str($request,'statut'); if(!in_array($statut,self::STATUTS_PAIEMENT,true)) throw new ApiException('Statut invalide');
        $update=['statut'=>$statut]; if($statut==='paye') $update['date_paiement']=now()->toDateTimeString();
        $changed=DB::table('paiements')->where('id',$id)->update($update); if($changed===0) throw ApiException::notFound('Paiement introuvable');
        return ApiResponse::success(['message'=>"Statut du paiement mis à jour ($statut)"]);
    }

    public function avis(Request $request): JsonResponse
    {
        $statut=In::queryStr($request,'statut');
        $query=DB::table('avis as a')->join('users as u1','u1.id','=','a.user_id')->join('users as u2','u2.id','=','a.transporteur_id')->select('a.*','u1.prenom as user_prenom','u1.nom as user_nom','u2.id as transporteur_id','u2.prenom as transporteur_prenom','u2.nom as transporteur_nom');
        if(in_array($statut,self::STATUTS_MODERATION,true)) $query->where('a.statut',$statut);
        $avis=$query->orderByDesc('a.date_avis')->limit(200)->get()->map(fn($row)=>(array)$row)->all();
        return ApiResponse::success($avis);
    }

    public function avisStatut(Request $request,int $id): JsonResponse
    {
        $statut=In::str($request,'statut'); if(!in_array($statut,self::STATUTS_MODERATION,true)) throw new ApiException('Statut invalide');
        $changed=DB::table('avis')->where('id',$id)->update(['statut'=>$statut]); if($changed===0) throw ApiException::notFound('Avis introuvable');
        return ApiResponse::success(['message'=>"Statut de l'avis mis à jour ($statut)"]);
    }

    public function avisDelete(int $id): JsonResponse
    {
        if(DB::table('avis')->where('id',$id)->delete()===0) throw ApiException::notFound('Avis introuvable');
        return ApiResponse::success(['message'=>'Avis supprimé']);
    }

    public function contactMessages(Request $request): JsonResponse
    {
        $repondu=In::queryStr($request,'repondu');
        $query=DB::table('messages_contact');
        if($repondu==='oui') $query->whereNotNull('reponse');
        if($repondu==='non') $query->whereNull('reponse');
        $messages=$query->orderByDesc('date_envoi')->limit(200)->get()->map(fn($row)=>(array)$row)->all();
        DB::table('messages_contact')->where('lu_par_admin',0)->update(['lu_par_admin'=>1]);
        return ApiResponse::success($messages);
    }

    public function contactRepondre(Request $request,int $id): JsonResponse
    {
        $reponse=In::str($request,'reponse'); if($reponse===''||mb_strlen($reponse)>5000) throw new ApiException('Réponse invalide');
        $changed=DB::table('messages_contact')->where('id',$id)->update(['reponse'=>$reponse,'date_reponse'=>now()->toDateTimeString(),'lu_par_utilisateur'=>0]);
        if($changed===0) throw ApiException::notFound('Message introuvable');
        return ApiResponse::success(['message'=>'Réponse envoyée']);
    }

    public function contactDelete(int $id): JsonResponse
    {
        if(DB::table('messages_contact')->where('id',$id)->delete()===0) throw ApiException::notFound('Message introuvable');
        return ApiResponse::success(['message'=>'Message supprimé']);
    }
}
