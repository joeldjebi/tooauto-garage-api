<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\QrcodeAssignment;
use App\Models\QrcodeGenerate;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WasabiService;

class QrcodeController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }


    /**
     * Liste des assignations de QR codes (historique du professionnel connecté)
     */
    public function index(Request $request): JsonResponse
    {
        $professionnel = auth('professionnels')->user();

        $assignments = QrcodeAssignment::where('professionnel_id', $professionnel->id)
            ->with(['qrcodeGenerate.vehicule', 'professionnel', 'etablissement', 'user', 'vehicule.marque'])
            ->orderByDesc('assigned_at')
            ->paginate($request->integer('per_page', 15));

        $assignments->setCollection(
            $assignments->getCollection()->map(function ($assignment) {
                return $this->attachAssignmentVehiculePhotoUrls($assignment);
            })
        );

        return response()->json($assignments);
    }

    private function attachAssignmentVehiculePhotoUrls($assignment)
    {
        if ($assignment->relationLoaded('vehicule') && $assignment->vehicule) {
            $assignment->setRelation(
                'vehicule',
                $this->attachVehiculePhotoUrls($assignment->vehicule)
            );
        }

        if ($assignment->relationLoaded('qrcodeGenerate') && $assignment->qrcodeGenerate) {
            $qrcodeGenerate = $assignment->qrcodeGenerate;

            if ($qrcodeGenerate->vehicule) {
                $qrcodeGenerate->setRelation(
                    'vehicule',
                    $this->attachVehiculePhotoUrls($qrcodeGenerate->vehicule)
                );
            }

            $assignment->setRelation('qrcodeGenerate', $qrcodeGenerate);
        }

        return $assignment;
    }

    private function attachVehiculePhotoUrls($vehicule)
    {
        $photos = $vehicule->photos;

        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($photos)) {
            $photos = [];
        }

        $vehicule->photos = array_values(array_filter(array_map(function ($photo) {
            return $photo ? $this->wasabiService->temporaryUrl($photo) : null;
        }, $photos)));

        return $vehicule;
    }


    /**
     * Attribuer un QR code à un véhicule lors d'un scan (professionnel = lavage/station)
     */
        public function assignByScanLavage(Request $request): JsonResponse
    {
        try {
            Log::info('Tentative d\'attribution de QR code', ['request' => $request->all()]);

            $validated = $request->validate([
                'qrcode' => 'required|string|max:255',
                'matricule' => 'required|string|max:50',
            ]);

            $professionnel = auth('professionnels')->user();
            if (! $professionnel) {
                Log::warning('Tentative d\'attribution sans authentification');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Non authentifié',
                ], 401);
            }

            $vehicule = Vehicule::firstWhere('matricule', $validated['matricule']);
            if (! $vehicule) {
                Log::warning('Véhicule non trouvé', ['matricule' => $validated['matricule']]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Véhicule introuvable.',
                ], 404);
            }

			if ($vehicule->qrcode_generate_id) {
                Log::warning('Ce véhicule a déjà un QR code attribué', ['matricule' => $vehicule->matricule]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce véhicule (matricule : '.$vehicule->matricule.') a déjà un QR code attribué. Un véhicule ne peut avoir qu\'un seul QR code.',
                ], 409);
            }

            $qrcode = QrcodeGenerate::where('qrcode', $validated['qrcode'])->first();
            if (! $qrcode) {
                Log::warning('QR code non trouvé', ['qrcode' => $validated['qrcode']]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'QR code invalide',
                ], 404);
            }

            if ($qrcode->is_assigned) {
                Log::warning('QR code déjà attribué', ['qrcode' => $qrcode->qrcode]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce QR code est déjà attribué',
                ], 409);
            }

            $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();

            if (! $etablissement) {
                Log::warning('Établissement non trouvé', ['professionnel_id' => $professionnel->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Établissement non trouvé. Créez votre établissement d\'abord.',
                ], 404);
            }

            $dejaAttribue = QrcodeAssignment::where('qrcode_id', $qrcode->id)
                ->where('user_id', $vehicule->user_id)
                ->exists();
            if ($dejaAttribue) {
                Log::warning('Ce QR code a déjà été attribué à ce véhicule', [
                    'qrcode' => $qrcode->qrcode,
                    'matricule' => $vehicule->matricule,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce QR code a déjà été attribué à ce véhicule (matricule : '.$vehicule->matricule.').',
                ], 409);
            }

            DB::beginTransaction();

            try {
                Log::info('Début de la transaction pour l\'attribution du QR code', [
                    'professionnel_id' => $professionnel->id,
                    'qrcode_id' => $qrcode->id,
                    'vehicule_id' => $vehicule->id,
                ]);

                $assignment = QrcodeAssignment::create([
                    'professionnel_id' => $professionnel->id,
                    'etablissement_id' => $etablissement->id,
                    'qrcode_id' => $qrcode->id,
                    'user_id' => $vehicule->user_id,
                    'assigned_at' => now(),
                ]);

                $qrcode->update([
                    'is_assigned' => true,
                    'assigned_at' => now(),
                ]);

                $vehicule->qrcode_generate_id = $qrcode->id;
                $vehicule->save();

                DB::commit();

                Log::info('QR code attribué avec succès', [
                    'qrcode' => $qrcode->qrcode,
                    'professionnel_id' => $professionnel->id,
                    'vehicule' => $vehicule->matricule,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'QR code attribué et historisé avec succès',
                    'data' => [
                        'qrcode' => $qrcode->qrcode,
                        'professionnel' => $professionnel->prenoms.' '.$professionnel->nom,
                        'assigned_at' => $assignment->assigned_at->toIso8601String(),
                        'vehicule' => $vehicule->matricule,
                    ],
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Erreur lors de l\'attribution du QR code', ['error' => $e->getMessage()]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'attribution du QR code',
                    'error' => $e->getMessage(),
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation échouée', ['errors' => $e->errors()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur inattendue', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur inattendue est survenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
