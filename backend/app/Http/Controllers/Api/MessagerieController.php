<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAdmin;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Files;
use App\Support\In;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagerieController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $conversations = DB::table('users as u')
            ->select('u.id', 'u.nom', 'u.prenom', 'u.photo_profil')
            ->selectSub(
                DB::table('messages as m2')
                    ->select('contenu')
                    ->where(function ($q) use ($uid) {
                        $q->whereRaw('m2.expediteur_id = u.id')->where('m2.destinataire_id', $uid);
                    })
                    ->orWhere(function ($q) use ($uid) {
                        $q->where('m2.expediteur_id', $uid)->whereRaw('m2.destinataire_id = u.id');
                    })
                    ->orderByDesc('m2.date_envoi')
                    ->limit(1),
                'dernier_message'
            )
            ->selectSub(
                DB::table('messages as m3')
                    ->select('date_envoi')
                    ->where(function ($q) use ($uid) {
                        $q->whereRaw('m3.expediteur_id = u.id')->where('m3.destinataire_id', $uid);
                    })
                    ->orWhere(function ($q) use ($uid) {
                        $q->where('m3.expediteur_id', $uid)->whereRaw('m3.destinataire_id = u.id');
                    })
                    ->orderByDesc('m3.date_envoi')
                    ->limit(1),
                'derniere_date'
            )
            ->selectSub(
                DB::table('messages as m4')
                    ->selectRaw('COUNT(*)')
                    ->whereRaw('m4.expediteur_id = u.id')
                    ->where('m4.destinataire_id', $uid)
                    ->where('m4.lu', 0),
                'non_lus'
            )
            ->where('u.id', '!=', $uid)
            ->whereExists(function ($q) use ($uid) {
                $q->select(DB::raw(1))
                  ->from('messages as m5')
                  ->where(function ($w) use ($uid) {
                      $w->whereRaw('m5.expediteur_id = u.id')->where('m5.destinataire_id', $uid);
                  })
                  ->orWhere(function ($w) use ($uid) {
                      $w->where('m5.expediteur_id', $uid)->whereRaw('m5.destinataire_id = u.id');
                  });
            })
            ->orderByDesc('derniere_date')
            ->get();

        $data = $conversations->map(function ($c) {
            $c = (array) $c;
            $c['photo_url'] = Files::url($c['photo_profil']);
            unset($c['photo_profil']);
            $c['non_lus'] = (int) $c['non_lus'];
            return $c;
        })->all();

        return ApiResponse::success($data);
    }

    public function messages(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $destinataireId = In::queryInt($request, 'destinataire_id');
        $colisId = In::queryInt($request, 'colis_id') ?: null;
        $voyageId = In::queryInt($request, 'voyage_id') ?: null;
        if ($destinataireId <= 0) throw new ApiException('destinataire_id est obligatoire');

        $query = DB::table('messages as m')
            ->leftJoin('users as u', 'm.expediteur_id', '=', 'u.id')
            ->select('m.*', 'u.nom', 'u.prenom', 'u.photo_profil')
            ->where(function ($q) use ($uid, $destinataireId) {
                $q->where(function ($w) use ($uid, $destinataireId) {
                    $w->where('m.expediteur_id', $uid)->where('m.destinataire_id', $destinataireId);
                })->orWhere(function ($w) use ($uid, $destinataireId) {
                    $w->where('m.expediteur_id', $destinataireId)->where('m.destinataire_id', $uid);
                });
            });
        if ($colisId) $query->where('m.colis_id', $colisId);
        elseif ($voyageId) $query->where('m.voyage_id', $voyageId);

        $messages = $query->orderBy('m.date_envoi')->limit(500)->get();
        $data = $messages->map(function ($m) {
            $m = (array) $m;
            $m['fichier_url'] = Files::url($m['fichier'] ?? null);
            $m['photo_url'] = Files::url($m['photo_profil'] ?? null);
            unset($m['fichier'], $m['photo_profil']);
            return $m;
        })->all();

        Message::where('expediteur_id', $destinataireId)->where('destinataire_id', $uid)->where('lu', 0)->update(['lu' => 1]);

        return ApiResponse::success($data);
    }

    public function send(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $destinataireId = In::int($request, 'destinataire_id');
        $contenu = In::str($request, 'contenu');
        $colisId = In::int($request, 'colis_id') ?: null;
        $voyageId = In::int($request, 'voyage_id') ?: null;

        if ($destinataireId <= 0 || $destinataireId === $uid) throw new ApiException('Destinataire invalide');
        if (!User::where('id', $destinataireId)->exists()) throw ApiException::notFound('Destinataire introuvable');
        if ($contenu === '' && !$request->hasFile('fichier')) throw new ApiException('Message vide');
        if (mb_strlen($contenu) > 5000) throw new ApiException('Message trop long (5000 caractères max)');

        $fichier = Uploads::storeImage($request->file('fichier'), 'messages', 'msg');

        Message::create([
            'expediteur_id' => $uid,
            'destinataire_id' => $destinataireId,
            'contenu' => $contenu,
            'fichier' => $fichier,
            'colis_id' => $colisId,
            'voyage_id' => $voyageId,
        ]);

        return ApiResponse::success(['message' => 'Message envoyé'], 201);
    }

    public function adminChat(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;
        $isAdmin = $request->user()->isAdmin();

        if ($isAdmin) {
            $counterpart = In::queryInt($request, 'user_id');
            if ($counterpart <= 0) throw new ApiException('user_id est obligatoire pour un admin');
        } else {
            $counterpart = $this->adminId();
            if (!$counterpart) return ApiResponse::success([]);
        }

        $messages = DB::table('messages_admin as m')
            ->join('users as u', 'm.expediteur_id', '=', 'u.id')
            ->select('m.*', 'u.nom', 'u.prenom')
            ->where(function ($q) use ($uid, $counterpart) {
                $q->where(function ($w) use ($uid, $counterpart) {
                    $w->where('m.expediteur_id', $uid)->where('m.destinataire_id', $counterpart);
                })->orWhere(function ($w) use ($uid, $counterpart) {
                    $w->where('m.expediteur_id', $counterpart)->where('m.destinataire_id', $uid);
                });
            })
            ->orderBy('m.date_envoi')
            ->limit(500)
            ->get();

        $data = $messages->map(function ($m) use ($uid) {
            $m = (array) $m;
            $m['moi'] = ((int) $m['expediteur_id'] === $uid);
            return $m;
        })->all();

        MessageAdmin::where('expediteur_id', $counterpart)->where('destinataire_id', $uid)->where('lu', 0)->update(['lu' => 1]);

        return ApiResponse::success($data);
    }

    public function adminChatSend(Request $request): JsonResponse
    {
        $user = $request->user();
        $uid = (int) $user->id;
        $contenu = In::str($request, 'contenu');
        if ($contenu === '' || mb_strlen($contenu) > 5000) throw new ApiException('Message vide ou trop long (5000 caractères max)');

        if ($user->isAdmin()) {
            $destinataire = In::int($request, 'destinataire_id');
            if ($destinataire <= 0 || !User::where('id', $destinataire)->exists()) throw new ApiException('Destinataire invalide');
        } else {
            $destinataire = $this->adminId();
            if (!$destinataire) throw ApiException::conflict('Aucun administrateur disponible');
        }

        MessageAdmin::create([
            'expediteur_id' => $uid,
            'destinataire_id' => $destinataire,
            'contenu' => $contenu,
        ]);

        return ApiResponse::success(['message' => 'Message envoyé'], 201);
    }

    public function adminConversations(Request $request): JsonResponse
    {
        $adminId = (int) $request->user()->id;
        $conversations = DB::table('users as u')
            ->select('u.id as user_id', 'u.nom', 'u.prenom', 'u.email', 'u.photo_profil')
            ->selectSub(
                DB::table('messages_admin as m2')
                    ->select('contenu')
                    ->where(function ($q) use ($adminId) {
                        $q->whereRaw('m2.expediteur_id = u.id')->where('m2.destinataire_id', $adminId);
                    })
                    ->orWhere(function ($q) use ($adminId) {
                        $q->where('m2.expediteur_id', $adminId)->whereRaw('m2.destinataire_id = u.id');
                    })
                    ->orderByDesc('m2.date_envoi')
                    ->limit(1),
                'dernier_message'
            )
            ->selectSub(
                DB::table('messages_admin as m3')
                    ->select('date_envoi')
                    ->where(function ($q) use ($adminId) {
                        $q->whereRaw('m3.expediteur_id = u.id')->where('m3.destinataire_id', $adminId);
                    })
                    ->orWhere(function ($q) use ($adminId) {
                        $q->where('m3.expediteur_id', $adminId)->whereRaw('m3.destinataire_id = u.id');
                    })
                    ->orderByDesc('m3.date_envoi')
                    ->limit(1),
                'derniere_date'
            )
            ->selectSub(
                DB::table('messages_admin as m4')
                    ->selectRaw('COUNT(*)')
                    ->whereRaw('m4.expediteur_id = u.id')
                    ->where('m4.destinataire_id', $adminId)
                    ->where('m4.lu', 0),
                'non_lus'
            )
            ->where('u.id', '!=', $adminId)
            ->whereNotIn('u.role', ['admin','super_admin'])
            ->whereExists(function ($q) use ($adminId) {
                $q->select(DB::raw(1))
                  ->from('messages_admin as m5')
                  ->where(function ($w) use ($adminId) {
                      $w->whereRaw('m5.expediteur_id = u.id')->where('m5.destinataire_id', $adminId);
                  })
                  ->orWhere(function ($w) use ($adminId) {
                      $w->where('m5.expediteur_id', $adminId)->whereRaw('m5.destinataire_id = u.id');
                  });
            })
            ->orderByDesc('derniere_date')
            ->get();

        $data = $conversations->map(function ($c) {
            $c = (array) $c;
            $c['photo_url'] = Files::url($c['photo_profil']);
            unset($c['photo_profil']);
            $c['non_lus'] = (int) $c['non_lus'];
            return $c;
        })->all();

        return ApiResponse::success($data);
    }

    private function adminId(): ?int
    {
        // Priorité au super_admin, sinon admin
        $id = User::where('role', 'super_admin')->orderBy('id')->value('id');
        if ($id) return (int) $id;
        $id = User::where('role', 'admin')->orderBy('id')->value('id');
        return $id !== null ? (int) $id : null;
    }
}
