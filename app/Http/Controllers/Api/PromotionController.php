<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    private function getEtablissement(): ?Etablissement
    {
        $professionnel = auth('professionnels')->user();

        return Etablissement::where('professionnel_id', $professionnel->id)->first();
    }

    /**
     * Liste des promotions de l'établissement.
     */
    public function index(Request $request): JsonResponse
    {
        $etablissement = $this->getEtablissement();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $promotions = Promotion::where('etablissement_id', $etablissement->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($promotions);
    }

    /**
     * Détail d'une promotion.
     */
    public function show(int $id): JsonResponse
    {
        $etablissement = $this->getEtablissement();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $promotion = Promotion::where('etablissement_id', $etablissement->id)->find($id);
        if (! $promotion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion introuvable.',
            ], 404);
        }

        return response()->json($promotion);
    }

    /**
     * Créer une promotion.
     */
    public function store(Request $request): JsonResponse
    {
        $etablissement = $this->getEtablissement();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $validated = $request->validate([
            'libelle' => 'required|string|max:200',
            'mobile' => 'required|string|max:20',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'image' => 'required|string|max:100',
            'statut' => 'nullable|integer|in:0,1',
            'description' => 'nullable|string',
        ]);

        $promotion = Promotion::create([
            ...$validated,
            'statut' => $validated['statut'] ?? 0,
            'etablissement_id' => $etablissement->id,
            'created_by' => auth('professionnels')->id(),
        ]);

        return response()->json($promotion, 201);
    }

    /**
     * Mettre à jour une promotion.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $etablissement = $this->getEtablissement();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $promotion = Promotion::where('etablissement_id', $etablissement->id)->find($id);
        if (! $promotion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'libelle' => 'sometimes|string|max:200',
            'mobile' => 'sometimes|string|max:20',
            'date_debut' => 'sometimes|date',
            'date_fin' => [
                'sometimes',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $promotion) {
                    $debut = $request->input('date_debut', $promotion->date_debut?->format('Y-m-d'));
                    if ($debut && $value < $debut) {
                        $fail('La date de fin doit être postérieure ou égale à la date de début.');
                    }
                },
            ],
            'image' => 'sometimes|string|max:100',
            'statut' => 'nullable|integer|in:0,1',
            'description' => 'nullable|string',
        ]);

        $promotion->update($validated);

        return response()->json($promotion);
    }

    /**
     * Supprimer une promotion.
     */
    public function destroy(int $id): JsonResponse
    {
        $etablissement = $this->getEtablissement();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $promotion = Promotion::where('etablissement_id', $etablissement->id)->find($id);
        if (! $promotion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promotion introuvable.',
            ], 404);
        }

        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promotion supprimée.',
        ]);
    }
}
