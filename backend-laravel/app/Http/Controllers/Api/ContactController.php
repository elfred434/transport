<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\MessageContact;
use App\Support\ApiResponse;
use App\Support\In;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contact : formulaire public (sans authentification) + réponses de
 * l'administration rattachées à l'utilisateur connecté par son email.
 *
 * Port fidèle du contrôleur `contact.php` de l'API d'origine.
 */
class ContactController extends Controller
{
    /** POST /api/contact — envoi public. */
    public function send(Request $request): JsonResponse
    {
        $nom = In::str($request, 'nom');
        $email = $this->validateEmail(In::str($request, 'email'));
        $message = In::str($request, 'message');

        if ($nom === '' || $message === '') {
            throw new ApiException('Tous les champs sont obligatoires');
        }
        if (mb_strlen($message) > 5000) {
            throw new ApiException('Message trop long (5000 caractères max)');
        }

        MessageContact::create([
            // Échappement à l'insertion pour `nom` : comportement historique.
            // Le message reste brut (échappement à l'affichage).
            'nom' => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
            'email' => $email,
            'message' => $message,
        ]);

        return ApiResponse::success(
            ['message' => 'Votre message a bien été envoyé à l\'agence'],
            201
        );
    }

    /** GET /api/contact/reponses — réponses reçues par l'utilisateur connecté. */
    public function reponses(Request $request): JsonResponse
    {
        $email = $request->user()->email;

        $reponses = DB::table('messages_contact')
            ->select('id', 'nom', 'email', 'message', 'reponse', 'date_envoi', 'date_reponse')
            ->where('email', $email)
            ->whereNotNull('reponse')
            ->orderByDesc('date_reponse')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        DB::table('messages_contact')
            ->where('email', $email)
            ->whereNotNull('reponse')
            ->update(['lu_par_utilisateur' => 1]);

        return ApiResponse::success($reponses);
    }

    /** Même validation que `validate_email()` de l'API d'origine. */
    private function validateEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Adresse email invalide');
        }

        return $email;
    }
}
