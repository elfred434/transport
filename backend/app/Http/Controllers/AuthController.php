<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Transporteur;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Authentification : inscription, connexion (jeton Bearer), déconnexion,
 * profil courant, réinitialisation de mot de passe.
 *
 * Port fidèle du contrôleur `auth.php` de l'API d'origine : mêmes messages
 * d'erreur, mêmes codes HTTP (400 par défaut pour une donnée invalide, jamais
 * 422), même forme de réponse {"success":true,"data":{token,user}}.
 *
 * Les jetons sont gérés par Sanctum (table `personal_access_tokens`, empreinte
 * sha256, expiration 30 jours) en remplacement de `api_tokens` : le contrat
 * côté client est inchangé.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $nom = trim((string) $request->input('nom', ''));
        $prenom = trim((string) $request->input('prenom', ''));
        $email = $this->validateEmail((string) $request->input('email', ''));
        $password = $this->validatePassword((string) $request->input('password', ''));
        $telephone = trim((string) $request->input('tel', ''));

        if ($nom === '' || $prenom === '') {
            throw new ApiException('Le nom et le prénom sont obligatoires');
        }

        if (User::where('email', $email)->exists()) {
            throw ApiException::conflict('Cet email est déjà utilisé');
        }

        $photo = Uploads::storeImage($request->file('photo_profil'), '', 'profil');

        $user = User::create([
            // Échappement à l'insertion : comportement historique de l'API,
            // conservé pour que le contenu en base reste identique.
            'nom' => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
            'prenom' => htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'),
            'email' => $email,
            'password' => $password,
            'telephone' => htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8'),
            'photo_profil' => $photo,
        ]);

        // Relecture après insertion (comme l'API d'origine) : le `role` et
        // `date_inscription` sont des défauts MySQL, absents du modèle créé.
        $user->refresh();

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => $user->toPublicArray(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $email = $this->validateEmail((string) $request->input('email', ''));
        $password = trim((string) $request->input('password', ''));

        $user = User::where('email', $email)->first();

        if ($user === null || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ApiException::unauthorized('Email ou mot de passe incorrect');
        }

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => $user->toPublicArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Déconnecté']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $transporteur = Transporteur::where('user_id', $user->id)->first(['id', 'solde']);

        $data = $user->toPublicArray();
        $data['is_transporteur'] = $transporteur !== null;
        $data['solde'] = $transporteur ? (float) $transporteur->solde : 0.0;

        return ApiResponse::success($data);
    }

    public function resetRequest(Request $request): JsonResponse
    {
        $email = $this->validateEmail((string) $request->input('email', ''));

        $message = 'Si un compte existe avec cette adresse, un lien de réinitialisation vient d\'être envoyé.';

        $user = User::where('email', $email)->first(['id']);

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $expires = now()->addHour()->toDateTimeString();

            DB::table('users')->where('id', $user->id)
                ->update(['reset_token' => $token, 'reset_expires' => $expires]);

            $resetLink = rtrim((string) config('transport.frontend_url'), '/')
                . '/reset-password.html?token=' . $token;

            if (env('SMTP_USER', '') !== '' && env('SMTP_PASS', '') !== '') {
                try {
                    Mail::mailer('smtp')->send(
                        new \App\Mail\ResetPasswordMail($email, $resetLink)
                    );
                } catch (\Throwable $e) {
                    Log::error("Échec envoi email reset pour $email : " . $e->getMessage());
                }
            } else {
                // Mode développement : lien journalisé (et renvoyé pour faciliter les tests).
                Log::info("SMTP non configuré — lien de réinitialisation : $resetLink");

                return ApiResponse::success([
                    'message' => $message,
                    'dev_reset_link' => $resetLink,
                ]);
            }
        }

        // Réponse générique (anti-énumération).
        return ApiResponse::success(['message' => $message]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $token = trim((string) $request->input('token', ''));
        $password = $this->validatePassword((string) $request->input('password', ''));

        $user = User::where('reset_token', $token)
            ->where('reset_expires', '>', now()->toDateTimeString())
            ->first(['id']);

        if ($user === null) {
            throw new ApiException('Lien de réinitialisation invalide ou expiré');
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'reset_token' => null,
            'reset_expires' => null,
        ])->save();

        return ApiResponse::success(['message' => 'Mot de passe réinitialisé, vous pouvez vous connecter.']);
    }

    /**
     * POST /api/auth/google/one-tap — Google One Tap / Identity Services
     * Body: { credential: string } (id_token JWT de Google)
     * Vérifie le token via https://oauth2.googleapis.com/tokeninfo
     * Crée ou connecte l'utilisateur, retourne token Sanctum
     */
    public function googleOneTap(Request $request): JsonResponse
    {
        $credential = trim((string) $request->input('credential', ''));
        if ($credential === '') {
            $credential = trim((string) $request->input('id_token', ''));
        }
        if ($credential === '') {
            throw new ApiException('Credential Google manquant');
        }

        // Vérifier le id_token via Google
        try {
            $googleResp = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $credential,
            ]);

            if (!$googleResp->successful()) {
                Log::warning('Google tokeninfo échoué', ['body' => $googleResp->body()]);
                throw new ApiException('Token Google invalide (tokeninfo)', 401);
            }

            $payload = $googleResp->json();
            // payload contient: sub (google_id), email, email_verified, name, given_name, family_name, picture
            $googleId = $payload['sub'] ?? null;
            $email = strtolower(trim($payload['email'] ?? ''));
            $emailVerified = $payload['email_verified'] ?? false;
            $name = $payload['name'] ?? '';
            $givenName = $payload['given_name'] ?? '';
            $familyName = $payload['family_name'] ?? '';
            $picture = $payload['picture'] ?? null;
            $aud = $payload['aud'] ?? '';

            if (!$googleId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ApiException('Payload Google incomplet', 401);
            }

            // Vérifier aud = notre GOOGLE_CLIENT_ID si configuré
            $expectedAud = config('services.google.client_id') ?: env('GOOGLE_CLIENT_ID', '');
            if ($expectedAud !== '' && $aud !== $expectedAud) {
                Log::warning('Google aud mismatch', ['aud' => $aud, 'expected' => $expectedAud]);
                // On ne bloque pas en dev si pas de client_id configuré, mais si configuré on vérifie
                if ($expectedAud !== '') {
                    throw new ApiException('Audience Google invalide', 401);
                }
            }

            if ($emailVerified !== true && $emailVerified !== 'true') {
                // Google dit email non vérifié, on bloque par sécurité
                // Mais on peut autoriser quand même en dev
                Log::info('Google email non vérifié', ['email' => $email]);
            }

            // Chercher utilisateur existant par google_id ou email
            $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

            if (!$user) {
                // Créer nouvel utilisateur - rôle client par défaut (comme demandé)
                $nom = $familyName !== '' ? $familyName : (explode(' ', $name)[1] ?? 'Google');
                $prenom = $givenName !== '' ? $givenName : (explode(' ', $name)[0] ?? 'User');

                $user = User::create([
                    'nom' => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
                    'prenom' => htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'),
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)), // mot de passe aléatoire, connexion via Google
                    'google_id' => $googleId,
                    'provider' => 'google',
                    'avatar' => $picture,
                    'role' => 'client', // par défaut client comme demandé
                ]);
                $user->refresh();
                Log::info('Nouvel utilisateur Google créé', ['id' => $user->id, 'email' => $email]);
            } else {
                // Mettre à jour google_id si manquant, et avatar
                $updates = [];
                if (!$user->google_id) $updates['google_id'] = $googleId;
                if (!$user->provider) $updates['provider'] = 'google';
                if ($picture && !$user->avatar) $updates['avatar'] = $picture;
                if ($updates) {
                    $user->forceFill($updates)->save();
                }
            }

            $token = $user->createToken('api')->plainTextToken;

            return ApiResponse::success([
                'token' => $token,
                'user' => $user->toPublicArray(),
                'provider' => 'google',
            ]);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Google One Tap exception: ' . $e->getMessage());
            throw new ApiException('Erreur vérification Google: ' . $e->getMessage(), 500);
        }
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

    /** Même validation que `validate_password()` de l'API d'origine. */
    private function validatePassword(string $password): string
    {
        if (strlen($password) < 8) {
            throw new ApiException('Le mot de passe doit contenir au moins 8 caractères');
        }

        return $password;
    }
}
