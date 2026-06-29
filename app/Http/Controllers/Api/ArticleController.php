<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Etablissement;
use App\Services\WasabiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }

    /**
     * Récupère l'établissement du professionnel connecté.
     */
    private function getEtablissement(): ?Etablissement
    {
        $professionnel = auth('professionnels')->user();

        return Etablissement::where('professionnel_id', $professionnel->id)->first();
    }

    /**
     * Liste des articles de l'établissement du professionnel connecté.
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

        $articles = Article::where('etablissement_id', $etablissement->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        $articles->setCollection(
            $articles->getCollection()->map(function ($article) {
                return $this->attachArticleImageUrl($article);
            })
        );

        return response()->json($articles);
    }

    private function attachArticleImageUrl(Article $article): Article
    {
        $article->image = ! empty($article->image)
            ? $this->wasabiService->temporaryUrl($article->image)
            : null;

        return $article;
    }

    /**
     * Affiche un article.
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

        $article = Article::where('etablissement_id', $etablissement->id)->find($id);
        if (! $article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article introuvable.',
            ], 404);
        }

        return response()->json($this->attachArticleImageUrl($article));
    }

    /**
     * Crée un nouvel article.
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
            'libelle' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'amount' => 'nullable|string|max:255',
            'etablissement_id' => 'nullable|integer',
        ]);

        $validated['image'] = $this->wasabiService->uploadFile(
            $request->file('image'),
            'uploads/articles',
            'article'
        );

        $article = Article::create([
            'libelle' => $validated['libelle'],
            'description' => $validated['description'],
            'image' => $validated['image'],
            'amount' => $validated['amount'] ?? null,
            'etablissement_id' => $etablissement->id,
            'created_by' => auth('professionnels')->id(),
        ]);

        return response()->json($this->attachArticleImageUrl($article), 201);
    }

    /**
     * Met à jour un article.
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

        $article = Article::where('etablissement_id', $etablissement->id)->find($id);
        if (! $article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'libelle' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'amount' => 'sometimes|string|max:255',
        ]);

        if ($request->hasFile('image')) {
            if (! empty($article->image)) {
                $this->wasabiService->deleteFile($article->image);
            }

            $validated['image'] = $this->wasabiService->uploadFile(
                $request->file('image'),
                'uploads/articles',
                'article'
            );
        }

        if (! empty($validated)) {
            $article->update($validated);
            $article->refresh();
        }

        return response()->json($this->attachArticleImageUrl($article));
    }

    /**
     * Supprime un article.
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

        $article = Article::where('etablissement_id', $etablissement->id)->find($id);
        if (! $article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article introuvable.',
            ], 404);
        }

        if (! empty($article->image)) {
            $this->wasabiService->deleteFile($article->image);
        }

        $article->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article supprimé.',
        ]);
    }
}
