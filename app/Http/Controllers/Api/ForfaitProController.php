<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForfaitPro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForfaitProController extends Controller
{
    /**
     * Liste des forfaits pro (pour affichage / souscription).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ForfaitPro::query()->orderBy('prix');

        if ($request->has('statut')) {
            $query->where('statut', $request->integer('statut'));
        }

        $forfaits = $query->paginate($request->integer('per_page', 15));

        return response()->json($forfaits);
    }
}
