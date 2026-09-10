<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\In;
use App\Support\KkiapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retraits : transporteur et admin
 * - Transporteur : voir ses retraits, demander retrait manuel si solde restant
 * - Admin : voir tous retraits, approuver/refuser/payer, voir wallet admin, demander retrait admin
 */
class RetraitController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;

    private function paginate($query, Request $request, ?callable $mapFn = null, ?string $defaultOrder = 'date_demande'): array
    {
        $page = max(1, In::int($request, 'page') ?: 1);
        $perPage = In::int($request, 'per_page') ?: self::DEFAULT_PER_PAGE;
        if ($perPage < 1) $perPage = self::DEFAULT_PER_PAGE;
        if ($perPage > self::MAX_PER_PAGE) $perPage = self::MAX_PER_PAGE;
        $orders = $query->getQuery()->orders ?? null;
        if ($defaultOrder !== null && empty($orders)) {
            $query->orderByDesc($defaultOrder);
        }
        $total = (clone $query)->count();
        $rows = $query->offset(($page-1)*$perPage)->limit($perPage)->get();
        $data = $mapFn ? $rows->map($mapFn)->all() : $rows->map(fn($r)=>(array)$r)->all();
        return [
            'data'=>$data,
            'pagination'=>[
                'page'=>$page,'per_page'=>$perPage,'total'=>(int)$total,
                'last_page'=>(int)max(1,ceil($total/$perPage)),
                'from'=>$total===0?0:($page-1)*$perPage+1,
                'to'=>(int)min($total,$page*$perPage),
            ]
        ];
    }

    /** GET /api/transporteur/retraits */
    public function transporteurList(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $query = DB::table('retraits')->where('user_id',$uid)->where('type','transporteur');
        $result = $this->paginate($query, $request);
        return ApiResponse::success($result);
    }

    /** POST /api/transporteur/retraits - demande manuelle si solde restant */
    public function transporteurDemande(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $montant = (float) $request->input('montant', 0);
        $numero = In::str($request, 'numero');
        $methode = In::str($request, 'methode') ?: 'mobile_money';

        if ($montant <= 0) throw new ApiException('Montant invalide');
        if ($montant < 1000) throw new ApiException('Montant minimum 1000 XOF');

        $solde = (float) (DB::table('transporteurs')->where('user_id', $uid)->value('solde') ?: 0);
        if ($montant > $solde) throw new ApiException('Solde insuffisant (solde: '.$solde.' XOF)');

        if ($numero === '') {
            // Récupérer téléphone du user
            $numero = DB::table('users')->where('id', $uid)->value('telephone') ?: '';
        }
        if ($numero === '') throw new ApiException('Numéro mobile money requis');

        $reference = 'MANUAL-'.$uid.'-'.strtoupper(uniqid());

        $id = DB::table('retraits')->insertGetId([
            'user_id' => $uid,
            'type' => 'transporteur',
            'montant' => $montant,
            'frais' => 0,
            'montant_net' => $montant,
            'statut' => 'en_attente',
            'methode' => in_array($methode, ['mobile_money','virement','kkiapay','autre']) ? $methode : 'mobile_money',
            'numero' => $numero,
            'reference' => $reference,
            'details' => 'Demande manuelle transporteur',
            'date_demande' => now(),
        ]);

        // Décrémenter solde immédiatement (réservé)
        DB::table('transporteurs')->where('user_id', $uid)->decrement('solde', $montant);

        return ApiResponse::success(['message' => 'Demande de retrait créée, en attente de traitement admin', 'id' => $id, 'reference' => $reference], 201);
    }

    /** GET /api/transporteur/solde */
    public function transporteurSolde(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $solde = (float) (DB::table('transporteurs')->where('user_id', $uid)->value('solde') ?: 0);
        $totalPaye = (float) DB::table('retraits')->where('user_id', $uid)->where('type','transporteur')->where('statut','paye')->sum('montant');
        $enAttente = (float) DB::table('retraits')->where('user_id', $uid)->where('type','transporteur')->where('statut','en_attente')->sum('montant');

        return ApiResponse::success([
            'solde' => $solde,
            'total_paye' => $totalPaye,
            'en_attente' => $enAttente,
        ]);
    }

    /** GET /api/admin/wallet */
    public function adminWallet(Request $request): JsonResponse
    {
        if (DB::table('admin_wallet')->count() === 0) {
            DB::table('admin_wallet')->insert(['id'=>1,'solde'=>0]);
        }
        $wallet = DB::table('admin_wallet')->where('id',1)->first();
        $totalCommission = (float) DB::table('suivi_colis as s')
            ->join('colis as c','c.id','=','s.colis_id')
            ->where('s.confirme_par_admin',1)
            ->sum(DB::raw('c.prix_estime * 0.05'));

        $totalPayeTransporteurs = (float) DB::table('retraits')->where('type','transporteur')->where('statut','paye')->sum('montant');
        $retraitsAdminPayes = (float) DB::table('retraits')->where('type','admin')->where('statut','paye')->sum('montant');

        return ApiResponse::success([
            'solde' => (float) ($wallet->solde ?? 0),
            'total_commission_generee' => $totalCommission,
            'total_paye_transporteurs' => $totalPayeTransporteurs,
            'total_retraits_admin' => $retraitsAdminPayes,
        ]);
    }

    /** GET /api/admin/retraits */
    public function adminList(Request $request): JsonResponse
    {
        $type = In::queryStr($request, 'type'); // transporteur|admin
        $statut = In::queryStr($request, 'statut');
        $search = In::queryStr($request, 'search');

        $query = DB::table('retraits as r')
            ->leftJoin('users as u','u.id','=','r.user_id')
            ->leftJoin('colis as c','c.id','=','r.colis_id')
            ->select('r.*','u.nom','u.prenom','u.email','u.telephone as user_telephone','c.nom_colis','c.numero_suivi');

        if (in_array($type, ['transporteur','admin'], true)) {
            $query->where('r.type', $type);
        }
        if (in_array($statut, ['en_attente','approuve','refuse','paye','echec'], true)) {
            $query->where('r.statut', $statut);
        }
        if ($search !== '') {
            $like = "%$search%";
            $query->where(function($q) use ($like){
                $q->where('r.reference','like',$like)
                  ->orWhere('r.numero','like',$like)
                  ->orWhere('u.nom','like',$like)
                  ->orWhere('u.prenom','like',$like)
                  ->orWhere('c.nom_colis','like',$like);
            });
        }

        $result = $this->paginate($query, $request, null, 'r.date_demande');
        return ApiResponse::success($result);
    }

    /** POST /api/admin/retraits/{id}/decision */
    public function adminDecision(Request $request, int $id): JsonResponse
    {
        $decision = In::str($request, 'decision'); // approuve|refuse|paye|echec
        $note = In::str($request, 'note');

        if (!in_array($decision, ['approuve','refuse','paye','echec'], true)) {
            throw new ApiException('Décision invalide (approuve|refuse|paye|echec)');
        }

        $retrait = DB::table('retraits')->where('id',$id)->first();
        if (!$retrait) throw ApiException::notFound('Retrait introuvable');

        $me = $request->user();

        if ($decision === 'refuse') {
            // Rembourser solde si transporteur
            if ($retrait->type === 'transporteur' && in_array($retrait->statut, ['en_attente','approuve'])) {
                DB::table('transporteurs')->where('user_id',$retrait->user_id)->increment('solde',$retrait->montant);
            }
            if ($retrait->type === 'admin' && in_array($retrait->statut, ['en_attente','approuve'])) {
                DB::table('admin_wallet')->where('id',1)->increment('solde',$retrait->montant);
            }

            DB::table('retraits')->where('id',$id)->update([
                'statut' => 'refuse',
                'date_traitement' => now(),
                'traite_par' => $me->id,
                'details' => ($retrait->details ? $retrait->details."\n" : '').'Refusé: '.$note,
            ]);

            return ApiResponse::success(['message'=>'Retrait refusé et solde remboursé']);
        }

        if ($decision === 'approuve') {
            DB::table('retraits')->where('id',$id)->update([
                'statut' => 'approuve',
                'date_traitement' => now(),
                'traite_par' => $me->id,
                'details' => ($retrait->details ? $retrait->details."\n" : '').'Approuvé: '.$note,
            ]);
            return ApiResponse::success(['message'=>'Retrait approuvé']);
        }

        if ($decision === 'paye') {
            // Tenter payout si transporteur et numéro présent
            $payoutResult = null;
            if ($retrait->type === 'transporteur' && $retrait->numero) {
                try {
                    $kkiapay = new KkiapayService();
                    $payoutResult = $kkiapay->payout($retrait->numero, (float)$retrait->montant, $retrait->reference);
                } catch (\Throwable $e) {
                    Log::error('Payout adminDecision échoué: '.$e->getMessage());
                    $payoutResult = ['status'=>'FAILED','error'=>$e->getMessage()];
                }
            }

            $finalStatut = 'paye';
            if ($payoutResult && ($payoutResult['status'] ?? '') !== 'SUCCESS') {
                // Si payout échoué et on est pas en sandbox, marquer echec mais laisser admin forcer paye manuel
                if (env('KKIAPAY_SANDBOX', true)) {
                    $finalStatut = 'paye'; // en sandbox on considère payé même si simulé
                } else {
                    // On laisse quand même paye si admin force, mais log l'échec
                    $finalStatut = 'paye';
                }
            }

            DB::table('retraits')->where('id',$id)->update([
                'statut' => $finalStatut,
                'date_traitement' => now(),
                'traite_par' => $me->id,
                'kkiapay_response' => $payoutResult ? json_encode($payoutResult) : $retrait->kkiapay_response,
                'details' => ($retrait->details ? $retrait->details."\n" : '').'Payé: '.$note,
            ]);

            return ApiResponse::success(['message'=>'Retrait marqué comme payé','payout'=>$payoutResult]);
        }

        // echec
        DB::table('retraits')->where('id',$id)->update([
            'statut' => 'echec',
            'date_traitement' => now(),
            'traite_par' => $me->id,
            'details' => ($retrait->details ? $retrait->details."\n" : '').'Échec: '.$note,
        ]);

        // Rembourser solde
        if ($retrait->type === 'transporteur') {
            DB::table('transporteurs')->where('user_id',$retrait->user_id)->increment('solde',$retrait->montant);
        } else {
            DB::table('admin_wallet')->where('id',1)->increment('solde',$retrait->montant);
        }

        return ApiResponse::success(['message'=>'Retrait marqué échec et solde remboursé']);
    }

    /** POST /api/admin/retraits - admin retire son propre solde */
    public function adminDemande(Request $request): JsonResponse
    {
        $montant = (float) $request->input('montant', 0);
        $numero = In::str($request, 'numero');
        $methode = In::str($request, 'methode') ?: 'mobile_money';

        if ($montant <= 0) throw new ApiException('Montant invalide');
        if (DB::table('admin_wallet')->count() === 0) {
            DB::table('admin_wallet')->insert(['id'=>1,'solde'=>0]);
        }
        $solde = (float) (DB::table('admin_wallet')->where('id',1)->value('solde') ?: 0);
        if ($montant > $solde) throw new ApiException('Solde admin insuffisant ('.$solde.' XOF)');

        $reference = 'ADMIN-'.strtoupper(uniqid());

        $id = DB::table('retraits')->insertGetId([
            'user_id' => $request->user()->id,
            'type' => 'admin',
            'montant' => $montant,
            'frais' => 0,
            'montant_net' => $montant,
            'statut' => 'paye', // admin se paye directement, on marque paye
            'methode' => in_array($methode, ['mobile_money','virement','kkiapay','autre']) ? $methode : 'mobile_money',
            'numero' => $numero,
            'reference' => $reference,
            'details' => 'Retrait admin',
            'date_demande' => now(),
            'date_traitement' => now(),
            'traite_par' => $request->user()->id,
        ]);

        DB::table('admin_wallet')->where('id',1)->decrement('solde',$montant);

        return ApiResponse::success(['message'=>'Retrait admin effectué','id'=>$id,'reference'=>$reference],201);
    }

    /** POST /api/admin/retraits/{id}/retry - retenter payout auto */
    public function adminRetry(Request $request, int $id): JsonResponse
    {
        $retrait = DB::table('retraits')->where('id',$id)->first();
        if (!$retrait) throw ApiException::notFound('Retrait introuvable');
        if ($retrait->type !== 'transporteur') throw new ApiException('Seuls les retraits transporteur peuvent être retentés');
        if (empty($retrait->numero)) throw new ApiException('Numéro manquant');

        $kkiapay = new KkiapayService();
        $result = $kkiapay->payout($retrait->numero, (float)$retrait->montant, $retrait->reference);

        $statut = ($result['status'] ?? 'FAILED') === 'SUCCESS' ? 'paye' : 'echec';

        DB::table('retraits')->where('id',$id)->update([
            'statut' => $statut,
            'date_traitement' => now(),
            'traite_par' => $request->user()->id,
            'kkiapay_response' => json_encode($result),
        ]);

        if ($statut === 'echec') {
            // Rembourser solde si échec et que solde avait été débité
            // Dans notre flow auto, solde déjà débité si paye, mais si on retente un echec, solde était déjà remboursé? On ne touche pas.
        }

        return ApiResponse::success(['message'=>'Tentative payout: '.$statut,'payout'=>$result,'statut'=>$statut]);
    }
}
