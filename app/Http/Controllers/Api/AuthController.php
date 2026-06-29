<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\Professionnel;
use App\Models\Ville;
use App\Models\Commune;
use App\Models\CategorieService;
use App\Models\TypeDePrestation;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Inscription d'un nouveau professionnel
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenoms' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'mobile' => 'required|string|max:255|unique:professionnels,mobile',
            'email' => 'nullable|email|max:255|unique:professionnels,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $professionnel = Professionnel::create([
            'nom' => $validated['nom'],
            'prenoms' => $validated['prenoms'],
            'role' => $validated['role'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $token = auth('professionnels')->login($professionnel);
        $ttlMinutes = auth('professionnels')->factory()->getTTL();

        return response()->json([
            'message' => 'Inscription réussie',
            'professionnel' => $professionnel,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes)->getTimestamp() * 1000,
        ], 201);
    }

    /**
     * Connexion (mobile + password)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! $token = auth('professionnels')->attempt($validated)) {
            throw ValidationException::withMessages([
                'mobile' => ['Identifiants incorrects.'],
            ]);
        }

        $professionnel = auth('professionnels')->user();
        $ttlMinutes = auth('professionnels')->factory()->getTTL();

        return response()->json([
            'message' => 'Connexion réussie',
            'professionnel' => $professionnel,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes)->getTimestamp() * 1000,
        ]);
    }

    /**
     * Envoyer un code de réinitialisation par SMS.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'indicatif' => 'required|string|max:10',
            'mobile' => 'required|string|max:30',
        ]);

        $indicatif = $this->normalizeIndicatif($validated['indicatif']);
        $mobile = trim($validated['mobile']);
        $professionnel = $this->findProfessionnelByPhone($indicatif, $mobile);
        $smsResponse = null;

        if ($professionnel) {
            $code = (string) random_int(100000, 999999);

            PasswordResetCode::where('indicatif', $indicatif)
                ->where('mobile', $mobile)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            PasswordResetCode::create([
                'indicatif' => $indicatif,
                'mobile' => $mobile,
                'code' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
            ]);

            $message = strtoupper("Votre code de reinitialisation TOO AUTO : " . $code);
            $smsResponse = $this->sendSmsMtarget($message, $indicatif . $mobile);
        }

        return response()->json([
            'message' => 'Si ce numéro existe, un code de réinitialisation a été envoyé.',
            'professionnel_found' => (bool) $professionnel,
            'sms_response' => $smsResponse,
        ]);
    }

    /**
     * Réinitialiser le mot de passe avec le code reçu par SMS.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'indicatif' => 'required|string|max:10',
            'mobile' => 'required|string|max:30',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $indicatif = $this->normalizeIndicatif($validated['indicatif']);
        $mobile = trim($validated['mobile']);
        $professionnel = $this->findProfessionnelByPhone($indicatif, $mobile);

        if (! $professionnel) {
            throw ValidationException::withMessages([
                'mobile' => ['Code invalide ou expiré.'],
            ]);
        }

        $resetCode = PasswordResetCode::where('indicatif', $indicatif)
            ->where('mobile', $mobile)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $resetCode || $resetCode->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        if ($resetCode->attempts >= 5) {
            $resetCode->used_at = now();
            $resetCode->save();

            throw ValidationException::withMessages([
                'code' => ['Nombre de tentatives dépassé. Veuillez demander un nouveau code.'],
            ]);
        }

        if (! Hash::check($validated['code'], $resetCode->code)) {
            $resetCode->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        $professionnel->password = $validated['password'];
        $professionnel->save();

        $resetCode->used_at = now();
        $resetCode->save();

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }

    /**
     * Récupérer le professionnel connecté
     */
    public function me(): JsonResponse
    {
        return response()->json(auth('professionnels')->user());
    }

    /**
     * Mettre à jour le profil du professionnel connecté (nom, prenoms, email uniquement)
     */
    public function update(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenoms' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255|unique:professionnels,email,' . $professionnel->id,
        ]);

        $professionnel->fill($validated);
        $professionnel->save();

        return response()->json([
            'message' => 'Profil mis à jour',
            'professionnel' => $professionnel->fresh(),
        ]);
    }

    /**
     * Mettre à jour le mot de passe du professionnel connecté
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $validated = $request->validate([
            'current_password' => 'required|string|current_password:professionnels',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $professionnel->password = $validated['password'];
        $professionnel->save();

        return response()->json(['message' => 'Mot de passe mis à jour']);
    }

    /**
     * Déconnexion
     */
    public function logout(): JsonResponse
    {
        auth('professionnels')->logout();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Rafraîchir le token JWT.
     * POST /api/auth/refresh avec header Authorization: Bearer {token}.
     * Retourne un nouveau token valide (même durée que à l'émission).
     */
    public function refresh(): JsonResponse
    {
        $token = auth('professionnels')->refresh();
        $ttlMinutes = auth('professionnels')->factory()->getTTL();

        return response()->json([
            'message' => 'Token rafraîchi avec succès',
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes)->getTimestamp() * 1000,
        ]);
    }

    public function villes(): JsonResponse
    {
        $villes = Ville::all();

        return response()->json($villes);
    }

    public function communes(): JsonResponse
    {
        $communes = Commune::all();

        return response()->json($communes);
    }

    public function typeDePrestations(): JsonResponse
    {
        $typeDePrestations = TypeDePrestation::all();

        return response()->json($typeDePrestations);
    }

    public function categorieServices(): JsonResponse
    {
        $categorieServices = CategorieService::where('statut', 1)->get();

        return response()->json($categorieServices);
    }

    protected function findProfessionnelByPhone(string $indicatif, string $mobile): ?Professionnel
    {
        $mobileVariants = $this->mobileVariants($indicatif, $mobile);
        $query = Professionnel::whereIn('mobile', $mobileVariants);

        if (Schema::hasColumn('professionnels', 'indicatif')) {
            $query->where('indicatif', $indicatif);
        }

        return $query->first();
    }

    protected function normalizeIndicatif(string $indicatif): string
    {
        return ltrim(trim($indicatif), '+');
    }

    protected function sendSmsMtarget($message, $msisdn, $sender = 'TOO AUTO')
    {
        $url = 'https://api-public-2.mtarget.fr/messages';

        if (strpos($msisdn, '+') !== 0) {
            $msisdn = '+' . $msisdn;
        }

        $postData = http_build_query([
            'username' => 'bwantech',
            'password' => 'x7jyKG0IJRNH',
            'msisdn' => $msisdn,
            'msg' => $message,
            'sender' => $sender,
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new \Exception('Erreur cURL : ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Erreur HTTP : ' . $httpCode . ' - Réponse : ' . $response);
        }

        return $response;
    }

    protected function mobileVariants(string $indicatif, string $mobile): array
    {
        $mobile = trim($mobile);
        $mobileWithoutPlus = ltrim($mobile, '+');
        $mobileWithoutIndicatif = $mobileWithoutPlus;

        if (str_starts_with($mobileWithoutPlus, $indicatif)) {
            $mobileWithoutIndicatif = substr($mobileWithoutPlus, strlen($indicatif));
        }

        $mobileWithoutLeadingZero = ltrim($mobileWithoutIndicatif, '0');

        return array_values(array_unique(array_filter([
            $mobile,
            $mobileWithoutPlus,
            $mobileWithoutIndicatif,
            $mobileWithoutLeadingZero,
            '0' . $mobileWithoutLeadingZero,
            $indicatif . $mobileWithoutIndicatif,
            $indicatif . $mobileWithoutLeadingZero,
            '+' . $indicatif . $mobileWithoutIndicatif,
            '+' . $indicatif . $mobileWithoutLeadingZero,
        ])));
    }
}
