<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbonnementPro;
use App\Models\Etablissement;
use App\Models\ShareLocalisation;
use App\Models\ForfaitPro;
use App\Services\WasabiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EtablissementController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    /**
     * Créer ou mettre à jour l'établissement du professionnel connecté
     * Si le professionnel a déjà un établissement → mise à jour
     * Sinon → création
     */
    public function createOrUpdate(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $etablissement = Etablissement::firstOrNew(['professionnel_id' => $professionnel->id]);

        // Normaliser type_de_prestations (multipart peut envoyer valeur unique ou tableau)
        $this->normalizeTypeDePrestations($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'indicatif' => 'string|max:4',
            'mobile' => 'nullable|string|max:20|unique:etablissements,mobile,' . $etablissement->id,
            'mobile_fix' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:200|unique:etablissements,email,' . $etablissement->id,
            'description' => 'required|string',
            'logo' => [
                'nullable',
                function (string $attr, mixed $value, \Closure $fail): void {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail('Le fichier envoyé est invalide.');
                            return;
                        }

                        if (!str_starts_with((string) $value->getMimeType(), 'image/')) {
                            $fail('Le fichier doit être une image.');
                            return;
                        }

                        if ($value->getSize() > 5120 * 1024) {
                            $fail('Le fichier ne doit pas dépasser 5120 Ko.');
                        }
                    } elseif (is_string($value) && strlen($value) > 255) {
                        $fail('Le champ ne doit pas dépasser 255 caractères.');
                    }
                },
            ],
            'cover' => [
                'nullable',
                function (string $attr, mixed $value, \Closure $fail): void {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        if (!$value->isValid()) {
                            $fail('Le fichier envoyé est invalide.');
                            return;
                        }

                        if (!str_starts_with((string) $value->getMimeType(), 'image/')) {
                            $fail('Le fichier doit être une image.');
                            return;
                        }

                        if ($value->getSize() > 5120 * 1024) {
                            $fail('Le fichier ne doit pas dépasser 5120 Ko.');
                        }
                    } elseif (is_string($value) && strlen($value) > 255) {
                        $fail('Le champ ne doit pas dépasser 255 caractères.');
                    }
                },
            ],
            'logo_where_is_create' => 'integer|in:0,1',
            'cover_where_is_create' => 'integer|in:0,1',
            'adresse' => 'required|string|max:255',
            'adresse_map' => 'nullable|string|max:500',
            'longitude' => 'required|string|max:255',
            'latitude' => 'required|string|max:255',
            'pays_id' => 'required|integer|exists:pays,id',
            'ville_id' => 'required|integer|exists:villes,id',
            'commune_id' => 'required|integer|exists:communes,id',
            'specialite' => 'nullable|string',
            'type_etablissement_id' => 'required|integer|exists:type_etablissements,id',
            'categorie_service_id' => 'required|integer|exists:categorie_services,id',
            'is_whatsapp' => 'integer|in:0,1',
            'type_de_prestations' => 'required|array',
            'type_de_prestations.*' => 'integer',
            'service_mobile' => 'integer|in:0,1',
            'cover_create_by' => 'integer|in:1,2',
            'logo_create_by' => 'integer|in:1,2',
            'statut' => 'integer|in:0,1',
            'code_parrain' => 'nullable|string|max:20',
            'is_commercial' => 'integer|in:0,1',
        ], [
            'name.required' => 'Le nom est requis.',
            'description.required' => 'La description est requise.',
            'adresse.required' => 'L\'adresse est requise.',
            'longitude.required' => 'La longitude est requise.',
            'latitude.required' => 'La latitude est requise.',
            'type_de_prestations.required' => 'Les types de prestations sont requis.',
            'categorie_service_id.exists' => 'La catégorie de service sélectionnée est invalide.',
        ]);

        if ($request->hasFile('logo')) {
            if (! empty($etablissement->logo)) {
                $this->wasabiService->deleteFile($etablissement->logo);
            }

            $validated['logo'] = $this->wasabiService->uploadFile(
                $request->file('logo'),
                'uploads/etablissements',
                'logo'
            );
        }

        if ($request->hasFile('cover')) {
            if (! empty($etablissement->cover)) {
                $this->wasabiService->deleteFile($etablissement->cover);
            }

            $validated['cover'] = $this->wasabiService->uploadFile(
                $request->file('cover'),
                'uploads/etablissements',
                'cover'
            );
        }

        $etablissement->fill(array_merge($validated, [
            'professionnel_id' => $professionnel->id,
            'indicatif' => $validated['indicatif'] ?? '225',
            'logo_where_is_create' => $validated['logo_where_is_create'] ?? 0,
            'cover_where_is_create' => $validated['cover_where_is_create'] ?? 0,
            'is_whatsapp' => $validated['is_whatsapp'] ?? 1,
            'service_mobile' => $validated['service_mobile'] ?? 1,
            'cover_create_by' => $validated['cover_create_by'] ?? 1,
            'logo_create_by' => $validated['logo_create_by'] ?? 1,
            'statut' => $validated['statut'] ?? 1,
            'is_commercial' => $validated['is_commercial'] ?? 0,
        ]));

        $etablissement->save();

        // Bascule automatique au forfait free (id 1) si jamais utilisé
        $this->assignFreeForfaitIfEligible($etablissement);

        $etablissement->load(['pays', 'ville', 'commune', 'typeEtablissement', 'categorieService']);
        $etablissement = $this->attachEtablissementMediaUrls($etablissement);

        return response()->json([
            'message' => $etablissement->wasRecentlyCreated ? 'Établissement créé' : 'Établissement mis à jour',
            'etablissement' => $etablissement,
        ], $etablissement->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Normalise type_de_prestations en tableau (gère multipart avec valeurs multiples ou chaîne).
     */
    private function normalizeTypeDePrestations(Request $request): void
    {
        $value = $request->input('type_de_prestations');

        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            $request->merge(['type_de_prestations' => array_map('intval', $value)]);
            return;
        }

        if (is_string($value)) {
            $ids = array_map('intval', array_filter(explode(',', str_replace(['[', ']', ' '], ['', '', ''], $value))));
            if (! empty($ids)) {
                $request->merge(['type_de_prestations' => $ids]);
            }
            return;
        }

        if (is_numeric($value)) {
            $request->merge(['type_de_prestations' => [(int) $value]]);
        }
    }

    /**
     * Bascule l'établissement au forfait free (id 1) s'il ne l'a jamais utilisé.
     * Un établissement ne peut utiliser le forfait free qu'une seule fois.
     * La durée du forfait est en mois (duree dans forfait_pros).
     */
    private function assignFreeForfaitIfEligible(Etablissement $etablissement): void
    {
        $forfaitFreeId = 1;

        $alreadyUsedFree = AbonnementPro::where('etablissement_id', $etablissement->id)
            ->where('forfait_pro_id', $forfaitFreeId)
            ->exists();

        if ($alreadyUsedFree) {
            return;
        }

        $forfaitFree = ForfaitPro::find($forfaitFreeId);
        if (! $forfaitFree) {
            return;
        }

        $dateDebut = Carbon::today();
        $dateFin = $dateDebut->copy()->addMonths($forfaitFree->duree);

        AbonnementPro::create([
            'etablissement_id' => $etablissement->id,
            'forfait_pro_id' => $forfaitFreeId,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);
    }

    /**
     * Récupérer l'établissement du professionnel connecté
     */
    public function show(): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $etablissement = Etablissement::where('professionnel_id', $professionnel->id)
            ->with(['pays', 'ville', 'commune', 'typeEtablissement', 'categorieService'])
            ->first();

        if (! $etablissement) {
            return response()->json(['message' => 'Aucun établissement trouvé'], 404);
        }

        return response()->json($this->attachEtablissementMediaUrls($etablissement));
    }

    private function attachEtablissementMediaUrls(Etablissement $etablissement): Etablissement
    {
        $etablissement->logo = ! empty($etablissement->logo)
            ? $this->wasabiService->temporaryUrl($etablissement->logo)
            : null;

        $etablissement->cover = ! empty($etablissement->cover)
            ? $this->wasabiService->temporaryUrl($etablissement->cover)
            : null;

        return $etablissement;
    }

    public function share_localisations(int $id): JsonResponse
    {
        $etablissement = Etablissement::find($id);
        if (! $etablissement) {
            return response()->json(['message' => 'Établissement non trouvé'], 404);
        }

        $share_localisations = ShareLocalisation::where('etablissement_id', $id)
            ->with('usager')
            ->get(['id', 'usager_id', 'professionnel_id', 'etablissement_id', 'longitude', 'latitude', 'created_at', 'updated_at']);

        return response()->json($share_localisations);
    }
	public function share_localisation_delete(int $id): JsonResponse
    {
        $professionnel = auth('professionnels')->user();
        $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();
        if (! $etablissement) {
            return response()->json(['status' => 'error', 'message' => 'Établissement non trouvé'], 404);
        }

        $share_localisation = ShareLocalisation::where('id', $id)->where('etablissement_id', $etablissement->id)->first();
        if (! $share_localisation) {
            return response()->json(['status' => 'error', 'message' => 'Partage de localisation non trouvé'], 404);
        }

        $share_localisation->delete();
        return response()->json(['status' => 'success', 'message' => 'Partage de localisation supprimé'], 200);
    }

    public function updateMedias(Request $request, $id)
    {
        if (!$request->hasFile('logo') && !$request->hasFile('cover')) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => [
                    'media' => ['Veuillez envoyer au moins un fichier logo ou cover.'],
                ],
            ], 422);
        }

        $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'];
        $maxSizeInKilobytes = 8048;

        foreach (['logo', 'cover'] as $field) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les données fournies ne sont pas valides.',
                    'errors' => [
                        $field => ['Le fichier envoyé est invalide.'],
                    ],
                ], 422);
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, $allowedExtensions, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les données fournies ne sont pas valides.',
                    'errors' => [
                        $field => ['Le fichier doit être une image jpeg, png, jpg, gif, svg ou webp.'],
                    ],
                ], 422);
            }

            if ($file->getSize() > $maxSizeInKilobytes * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les données fournies ne sont pas valides.',
                    'errors' => [
                        $field => ['Le fichier ne doit pas dépasser 8048 Ko.'],
                    ],
                ], 422);
            }
        }

        $etablissement = Etablissement::find($id);

        if (!$etablissement) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun établissement trouvé avec cet ID.',
            ], 404);
        }

        $oldLogo = $etablissement->logo;
        $oldCover = $etablissement->cover;
        $newLogo = null;
        $newCover = null;

        try {
            if ($request->hasFile('logo')) {
                $newLogo = $this->wasabiService->uploadFile(
                    $request->file('logo'),
                    'etablissement/logo',
                    'logo'
                );

                $etablissement->logo = $newLogo;
            }

            if ($request->hasFile('cover')) {
                $newCover = $this->wasabiService->uploadFile(
                    $request->file('cover'),
                    'etablissement/cover',
                    'cover'
                );

                $etablissement->cover = $newCover;
            }

            $etablissement->save();

            if ($newLogo !== null) {
                $this->deleteEtablissementMedia($oldLogo);
            }

            if ($newCover !== null) {
                $this->deleteEtablissementMedia($oldCover);
            }

            $etablissement->logo_url = $this->getSignedEtablissementMediaUrl($etablissement->logo);
            $etablissement->cover_url = $this->getSignedEtablissementMediaUrl($etablissement->cover);

            return response()->json([
                'success' => true,
                'message' => "Les médias de l'établissement ont été mis à jour avec succès.",
                'etablissement' => $etablissement,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Erreur pendant la mise a jour des medias etablissement.', [
                'etablissement_id' => $id,
                'new_logo' => $newLogo,
                'new_cover' => $newCover,
                'error' => $e->getMessage(),
            ]);

            if ($newLogo !== null) {
                $this->deleteEtablissementMedia($newLogo);
            }

            if ($newCover !== null) {
                $this->deleteEtablissementMedia($newCover);
            }

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue pendant la mise à jour des médias de l'établissement.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function deleteEtablissementMedia(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        try {
            $this->wasabiService->deleteFile($path);
        } catch (\Throwable $e) {
            Log::warning('Impossible de supprimer un ancien media etablissement.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getSignedEtablissementMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            return $this->wasabiService->temporaryUrl($path);
        } catch (\Throwable $e) {
            Log::warning('Impossible de generer une URL signee pour un media etablissement.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
