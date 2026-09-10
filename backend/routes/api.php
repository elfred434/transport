<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\ColisController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RetraitController;
use App\Http\Controllers\Api\SuiviController;
use App\Http\Controllers\Api\VoyageController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $metier = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->count();
    return \App\Support\ApiResponse::success([
        'name' => config('app.name') . ' — API',
        'version' => '2.1',
        'endpoints' => $metier,
        'roles' => ['client','transporteur','admin','super_admin'],
    ]);
});

// Public
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/reset-request', [AuthController::class, 'resetRequest']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('auth/google/one-tap', [AuthController::class, 'googleOneTap']);
Route::post('contact', [ContactController::class, 'send']);
Route::post('webhooks/kkiapay', [PaiementController::class, 'webhook']);
Route::get('kkiapay/public-config', [PaiementController::class, 'kkiapayConfig']); // public config (only public_key + sandbox)

// Authentifié
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::get('transporteurs/{id}', [ProfileController::class, 'transporteur']);
    Route::get('transporteur-stats', [ProfileController::class, 'stats']);

    Route::get('colis/mine', [ColisController::class, 'mine']);
    Route::get('colis/available', [ColisController::class, 'available']);
    Route::get('colis/{id}', [ColisController::class, 'show'])->whereNumber('id');
    Route::post('colis', [ColisController::class, 'store']);
    Route::post('colis/{id}', [ColisController::class, 'update'])->whereNumber('id');
    Route::get('colis/{id}/voyages-compatibles', [ColisController::class, 'voyagesCompatibles'])->whereNumber('id');
    Route::get('colis/{id}/reservations', [ColisController::class, 'reservations'])->whereNumber('id');

    Route::get('suivi/{numero}', [SuiviController::class, 'show']);
    Route::post('suivi', [SuiviController::class, 'store']);

    Route::get('voyages/available', [VoyageController::class, 'available']);
    Route::get('voyages/mine', [VoyageController::class, 'mine']);
    Route::post('voyages', [VoyageController::class, 'store']);
    Route::post('reservations', [VoyageController::class, 'reserver']);
    Route::get('reservations/recues', [VoyageController::class, 'recues']);
    Route::post('reservations/{id}/action', [VoyageController::class, 'action'])->whereNumber('id');

    Route::get('conversations', [MessagerieController::class, 'conversations']);
    Route::get('messages', [MessagerieController::class, 'messages']);
    Route::post('messages', [MessagerieController::class, 'send']);

    Route::get('admin-chat', [MessagerieController::class, 'adminChat']);
    Route::post('admin-chat', [MessagerieController::class, 'adminChatSend']);

    Route::get('contact/reponses', [ContactController::class, 'reponses']);

    Route::get('paiements/colis/{colis_id}', [PaiementController::class, 'forColis'])->whereNumber('colis_id');
    Route::post('paiements/{id}/payer', [PaiementController::class, 'payer'])->whereNumber('id');
    Route::post('paiements/{id}/verify-kkiapay', [PaiementController::class, 'verifyKkiapay'])->whereNumber('id');
    Route::get('paiements/mine', [PaiementController::class, 'mine']);
    Route::get('kkiapay/config', [PaiementController::class, 'kkiapayConfig']);

    Route::get('transporteurs/{id}/avis', [AvisController::class, 'forTransporteur'])->whereNumber('id');
    Route::post('avis', [AvisController::class, 'store']);

    // Retraits transporteur (solde 95% auto)
    Route::get('transporteur/retraits', [RetraitController::class, 'transporteurList']);
    Route::get('transporteur/solde', [RetraitController::class, 'transporteurSolde']);
    Route::post('transporteur/retraits', [RetraitController::class, 'transporteurDemande']);

    // Administration : admin et super_admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('stats', [AdminController::class, 'stats']);
        Route::get('users', [AdminController::class, 'users']);
        Route::post('users', [AdminController::class, 'userCreate']);
        Route::delete('users/{id}', [AdminController::class, 'userDelete'])->whereNumber('id');
        Route::post('users/{id}/role', [AdminController::class, 'userUpdateRole'])->whereNumber('id');

        Route::get('colis', [AdminController::class, 'colis']);
        Route::post('colis/{id}/statut', [AdminController::class, 'colisStatut'])->whereNumber('id');
        Route::delete('colis/{id}', [AdminController::class, 'colisDelete'])->whereNumber('id');

        Route::post('suivi/{id}/livraison', [AdminController::class, 'livraisonDecision'])->whereNumber('id');
        Route::delete('suivi/{id}', [AdminController::class, 'suiviDelete'])->whereNumber('id');

        Route::get('voyages', [AdminController::class, 'voyages']);
        Route::post('voyages/{id}/statut', [AdminController::class, 'voyageStatut'])->whereNumber('id');
        Route::delete('voyages/{id}', [AdminController::class, 'voyageDelete'])->whereNumber('id');

        Route::get('transporteurs', [AdminController::class, 'transporteurs']);
        Route::delete('transporteurs/{id}', [AdminController::class, 'transporteurDelete'])->whereNumber('id');

        Route::get('paiements', [AdminController::class, 'paiements']);
        Route::post('paiements/{id}/statut', [AdminController::class, 'paiementStatut'])->whereNumber('id');

        Route::get('avis', [AdminController::class, 'avis']);
        Route::post('avis/{id}/statut', [AdminController::class, 'avisStatut'])->whereNumber('id');
        Route::delete('avis/{id}', [AdminController::class, 'avisDelete'])->whereNumber('id');

        Route::get('contact-messages', [AdminController::class, 'contactMessages']);
        Route::post('contact-messages/{id}/repondre', [AdminController::class, 'contactRepondre'])->whereNumber('id');
        Route::delete('contact-messages/{id}', [AdminController::class, 'contactDelete'])->whereNumber('id');

        // Wallet admin + retraits
        Route::get('wallet', [RetraitController::class, 'adminWallet']);
        Route::get('retraits', [RetraitController::class, 'adminList']);
        Route::post('retraits', [RetraitController::class, 'adminDemande']);
        Route::post('retraits/{id}/decision', [RetraitController::class, 'adminDecision'])->whereNumber('id');
        Route::post('retraits/{id}/retry', [RetraitController::class, 'adminRetry'])->whereNumber('id');

        // Notifications admin (signal livraisons)
        Route::get('notifications', [AdminController::class, 'notifications']);
        Route::post('notifications/{id}/read', [AdminController::class, 'notificationRead'])->whereNumber('id');
        Route::post('notifications/read-all', [AdminController::class, 'notificationsReadAll']);

        // Page dédiée : livraisons à confirmer (avec pagination + détails complets + bulk)
        Route::get('livraisons', [AdminController::class, 'livraisons']);
        Route::post('livraisons/bulk', [AdminController::class, 'livraisonsBulk']);
    });

    Route::get('admin-chat/conversations', [MessagerieController::class, 'adminConversations'])->middleware('admin');

    // Super Admin seulement : gestion des admins
    Route::middleware('super_admin')->prefix('super-admin')->group(function () {
        Route::get('admins', function (\Illuminate\Http\Request $r) {
            $users = \Illuminate\Support\Facades\DB::table('users')->whereIn('role',['admin','super_admin'])->select('id','nom','prenom','email','role','date_inscription')->orderByDesc('date_inscription')->get();
            return \App\Support\ApiResponse::success($users);
        });
        Route::get('roles', function () {
            return \App\Support\ApiResponse::success(['roles'=>['client','transporteur','admin','super_admin']]);
        });
    });
});
