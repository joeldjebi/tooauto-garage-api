<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\Marque;
use App\Models\Type_de_piece;
use App\Models\Sous_categorie_piece;
use App\Models\Categorie_piece;
use App\Models\Etablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\WasabiService;


class AnnonceController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();

        if (!$etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $query = Annonce::query()
            ->where('type_etablissement_id', $etablissement->type_etablissement_id)
            ->where('statut', 1)
            ->whereDoesntHave('annonceEtablissements', function ($q) use ($etablissement) {
                $q->where('etablissement_id', $etablissement->id)
                    ->where('is_visible', false);
            });

        $annonces = $query
            ->with([
                'usager:id,uuid,nom,prenoms,mobile,indicatif,avatar',
                'type_de_piece:id,libelle',
                'marque:id,libelle',
                'categorie_piece:id,libelle',
                'sous_categorie_piece:id,libelle'
            ])
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 15), 100));

        if ($annonces->isEmpty()) {
            $annonceIds = Annonce::where('type_etablissement_id', $etablissement->type_etablissement_id)
                ->where('statut', 1)
                ->pluck('id');

            $data = $annonceIds->map(fn ($id) => [
                'etablissement_id' => $etablissement->id,
                'annonce_id' => $id,
                'is_visible' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            DB::table('annonce_etablissements')->insertOrIgnore($data);

            $annonces = $query
                ->with([
                    'usager:id,uuid,nom,prenoms,mobile,indicatif,avatar',
                    'type_de_piece:id,libelle',
                    'marque:id,libelle',
                    'categorie_piece:id,libelle',
                    'sous_categorie_piece:id,libelle'
                ])
                ->orderByDesc('created_at')
                ->paginate(min($request->integer('per_page', 15), 100));
        }

        $annonces->setCollection(
            $annonces->getCollection()->map(function ($annonce) {
                return $this->attachAnnonceMediaUrls($annonce);
            })
        );

        return response()->json($annonces);
    }

    private function attachAnnonceMediaUrls($annonce)
    {
        if (!empty($annonce->image)) {
            $annonce->image = $this->wasabiService->temporaryUrl($annonce->image);
        }

        if ($annonce->relationLoaded('usager') && $annonce->usager) {
            $annonce->setRelation('usager', $this->attachUserAvatarUrl($annonce->usager));
        }

        return $annonce;
    }

    private function attachUserAvatarUrl($user)
    {
        if (!$user) {
            return $user;
        }

        if (!empty($user->avatar)) {
            $user->avatar = $this->wasabiService->temporaryUrl($user->avatar);
        }

        return $user;
    }

    /**
     * Masquer une annonce pour l'établissement du professionnel connecté.
     */
    public function hide(Request $request): JsonResponse
    {
        $request->validate([
            'annonce_id' => 'required|integer|exists:annonces,id',
        ]);

        $professionnel = auth('professionnels')->user();

        $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        DB::table('annonce_etablissements')->updateOrInsert(
            [
                'etablissement_id' => $etablissement->id,
                'annonce_id' => $request->annonce_id,
            ],
            [
                'is_visible' => false,
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Annonce masquée.',
        ]);
    }
}