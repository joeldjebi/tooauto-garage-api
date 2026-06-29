<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;
use App\Models\TypeAlert;
use App\Models\Marque;
use App\Models\Vehicule;
use Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\Etablissement;
use App\Services\WasabiService;


class VidangeController extends Controller
{
    public function __construct(private WasabiService $wasabiService)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth('professionnels')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User non authentifié'
            ], 401);
        }

        $etablissement = Etablissement::where(['professionnel_id' => $user->id])->first();

        if (!$etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $alerts = Alert::where('etablissement_id', $etablissement->id)
            ->with('vehicule', 'vehicule.marque', 'vehicule.chauffeur', 'typeAlert', 'user')
            ->orderBy('id', 'desc')
            ->get();

        if ($alerts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune alert enregistré pour le moment.',
            ], 404);
        }

        $alerts = $alerts->map(function ($alert) {
            if ($alert->vehicule) {
                $alert->setRelation('vehicule', $this->attachVehiculePhotoUrls($alert->vehicule));
            }

            if ($alert->user) {
                $alert->setRelation('user', $this->attachUserAvatarUrl($alert->user));
            }

            if ($alert->vehicule && $alert->vehicule->gestionnaire_de_flotte_id !== null) {
                if ($alert->vehicule->chauffeur) {
                    $alert->vehicule->chauffeur->makeHidden('password');
                }

                $alert->setRelation('chauffeur', $alert->vehicule->chauffeur);

                if ($alert->chauffeur) {
                    $alert->chauffeur->makeHidden('password');
                }

                $alert->unsetRelation('user');
            }

            return $alert;
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des alerts.',
            'alerts' => $alerts,
        ], 200);
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
     * Display a listing of the resource.
     */
    public function getLastVidangeByMatricule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth('professionnels')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User non authentifié'
            ], 401);
        }

        $etablissement = Etablissement::where('professionnel_id', $user->id)->first();

        if (!$etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Établissement non trouvé.',
            ], 404);
        }

        $vehicule = Vehicule::where('matricule', $request->matricule)->first();

        if (!$vehicule) {
            return response()->json([
                'success' => false,
                'message' => 'Véhicule non trouvé.',
            ], 404);
        }

        $alerts = Alert::where('vehicule_id', $vehicule->id)
            ->with('vehicule', 'vehicule.marque', 'vehicule.chauffeur', 'typeAlert', 'user')
            ->orderBy('id', 'desc')
            ->first();

        if (!$alerts) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune vidange trouvée pour ce véhicule.',
                'vidange' => $this->attachVehiculePhotoUrls($vehicule),
            ], 404);
        }

        if ($alerts->vehicule) {
            $alerts->setRelation('vehicule', $this->attachVehiculePhotoUrls($alerts->vehicule));
        }

        if ($alerts->user) {
            $alerts->setRelation('user', $this->attachUserAvatarUrl($alerts->user));
        }

        if ($alerts->vehicule && $alerts->vehicule->gestionnaire_de_flotte_id !== null) {
            if ($alerts->vehicule->chauffeur) {
                $alerts->vehicule->chauffeur->makeHidden('password');
            }

            $alerts->setRelation('chauffeur', $alerts->vehicule->chauffeur);

            if ($alerts->chauffeur) {
                $alerts->chauffeur->makeHidden('password');
            }

            $alerts->unsetRelation('user');
        }

        return response()->json([
            'success' => true,
            'message' => 'Dernière vidange trouvée.',
            'vidange' => $alerts,
        ], 200);
    }




    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'vehicule_id' => 'required|exists:vehicules,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
            'kilometrage' => 'nullable',
            'autres' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth('professionnels')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User non authentifié'
            ], 401);
        }

        $etablissement = Etablissement::where(['professionnel_id' => $user->id])
        ->first();

        if (!$etablissement) {
            return response()->json([
                'status' => 'error',
                'message' => 'Etablissement non trouvé.'
            ], 401);
        }

        $vehicule = Vehicule::find($request->vehicule_id);
        if (! $vehicule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Véhicule non trouvé.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Traitement du champ autres
            $autres = $request->autres;
            if (is_array($autres) || is_object($autres)) {
                $autres = json_encode($autres);
            }

            // Création d'une alert
            $alert = new Alert();
            $alert->vehicule_id = $vehicule->id;
            $alert->type_alert_id = 2;
            $alert->date_debut = $request->date_debut;
            $alert->date_fin = $request->date_fin;
            $alert->kilometrage = $request->kilometrage;
            $alert->autres = $autres;
            $alert->etablissement_id = $etablissement->id;
            $alert->professionnel_id = $user->id;
            $alert->user_id = $vehicule->user_id;

            $alert->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Alert enregistré avec succès.',
                'alert' => $alert,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de l'alert.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    public function getVehiculeByMatricule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'matricule' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth('professionnels')->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User non authentifié'
                ], 401);
            }

            $etablissement = Etablissement::where('professionnel_id', $user->id)->first();

            if (!$etablissement) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Établissement non trouvé.',
                ], 404);
            }

            $vehicule = Vehicule::with('user')
                ->where('matricule', $request->matricule)
                ->first();

            if (!$vehicule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Véhicule non trouvé.',
                ], 404);
            }

            $vehicule = $this->attachVehiculePhotoUrls($vehicule);

            if ($vehicule->user) {
                $vehicule->setRelation('user', $this->attachUserAvatarUrl($vehicule->user));
            }

            return response()->json([
                'success' => true,
                'message' => 'Véhicule trouvé avec succès.',
                'vehicule' => $vehicule,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la recherche du véhicule.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }



}
