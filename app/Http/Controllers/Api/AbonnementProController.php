<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbonnementPro;
use App\Models\Etablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbonnementProController extends Controller
{
    /**
     * Historique des abonnements de l'établissement du professionnel connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();
        if (! $etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $abonnements = AbonnementPro::where('etablissement_id', $etablissement->id)
            ->with('forfaitPro')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($abonnements);
    }
}
