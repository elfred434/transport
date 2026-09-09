<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\ColisController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SuiviController;
use App\Http\Controllers\Api\VoyageController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de l'API
|--------------------------------------------------------------------------
|
| Le préfixe `api` est appliqué automatiquement par Laravel : les chemins
| écrits ici sont donc servis sous /api/... , à l'identique de l'API PHP
| d'origine. Le contrat (chemins, verbes, formes de réponse) est figé par les
| suites tests/test_api.py et tests/test_frontend_integration.js.
|
| Verbes : l'API n'expose que GET, POST et DELETE — les mutations passent par
| POST, comme dans l'implémentation d'origine.
|
*/

/*
 * Racine de l'API : découverte.
 *
 * Le compteur exclut cette route elle-même et les routes utilitaires ajoutées
 * par le framework (Sanctum, health-check) pour rester égal au nombre
 * d'endpoints métier réellement exposés.
 */
Route::get('/', function () {
    $metier = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->count();

    return \App\Support\ApiResponse::success([
        'name' => config('app.name') . ' — API',
        'version' => '2.0',
        'endpoints' => $metier,
    ]);
});

// ---------------------------------------------------------------------------
// Authentification (public)
// ---------------------------------------------------------------------------
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/reset-request', [AuthController::class, 'resetRequest']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

// ---------------------------------------------------------------------------
// Contact (public)
// ---------------------------------------------------------------------------
Route::post('contact', [ContactController::class, 'send']);

// ---------------------------------------------------------------------------
// Authentifié
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Profil
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::get('transporteurs/{id}', [ProfileController::class, 'transporteur']);
    Route::get('transporteur-stats', [ProfileController::class, 'stats']);

    // Colis
    Route::get('colis/mine', [ColisController::class, 'mine']);
    Route::get('colis/available', [ColisController::class, 'available']);
    Route::get('colis/{id}', [ColisController::class, 'show'])->whereNumber('id');
    Route::post('colis', [ColisController::class, 'store']);
    Route::post('colis/{id}', [ColisController::class, 'update'])->whereNumber('id');
    Route::get('colis/{id}/voyages-compatibles', [ColisController::class, 'voyagesCompatibles'])->whereNumber('id');
    Route::get('colis/{id}/reservations', [ColisController::class, 'reservations'])->whereNumber('id');

    // Suivi
    Route::get('suivi/{numero}', [SuiviController::class, 'show']);
    Route::post('suivi', [SuiviController::class, 'store']);

    // Voyages & réservations
    Route::get('voyages/available', [VoyageController::class, 'available']);
    Route::get('voyages/mine', [VoyageController::class, 'mine']);
    Route::post('voyages', [VoyageController::class, 'store']);
    Route::post('reservations', [VoyageController::class, 'reserver']);
    Route::get('reservations/recues', [VoyageController::class, 'recues']);
    Route::post('reservations/{id}/action', [VoyageController::class, 'action'])->whereNumber('id');

    // Messagerie utilisateur <-> utilisateur
    Route::get('conversations', [MessagerieController::class, 'conversations']);
    Route::get('messages', [MessagerieController::class, 'messages']);
    Route::post('messages', [MessagerieController::class, 'send']);

    // Messagerie utilisateur <-> admin
    Route::get('admin-chat', [MessagerieController::class, 'adminChat']);
    Route::post('admin-chat', [MessagerieController::class, 'adminChatSend']);

    // Contact : réponses reçues
    Route::get('contact/reponses', [ContactController::class, 'reponses']);

    // Paiements
    Route::get('paiements/colis/{colis_id}', [PaiementController::class, 'forColis'])->whereNumber('colis_id');
    Route::post('paiements/{id}/payer', [PaiementController::class, 'payer'])->whereNumber('id');
    Route::get('paiements/mine', [PaiementController::class, 'mine']);

    // Avis
    Route::get('transporteurs/{id}/avis', [AvisController::class, 'forTransporteur'])->whereNumber('id');
    Route::post('avis', [AvisController::class, 'store']);

    // -----------------------------------------------------------------------
    // Administration
    // -----------------------------------------------------------------------
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('stats', [AdminController::class, 'stats']);

        Route::get('users', [AdminController::class, 'users']);
        Route::post('users', [AdminController::class, 'userCreate']);
        Route::delete('users/{id}', [AdminController::class, 'userDelete'])->whereNumber('id');

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
    });

    // Conversations admin (vue administration) — hors préfixe /admin,
    // comme dans l'API d'origine.
    Route::get('admin-chat/conversations', [MessagerieController::class, 'adminConversations'])
        ->middleware('admin');
});
