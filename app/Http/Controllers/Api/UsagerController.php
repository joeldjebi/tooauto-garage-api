<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\Parrain;
use App\Models\ReferralCode;
use App\Models\TypeDeVehicule;
use App\Models\TypeDeCarburant;
use App\Models\Marque;
use App\Models\Vehicule;
use Validator;
use App\Models\User;
use App\Models\TypeAlert;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UsagerController extends Controller
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Enregistrer un usager (par un professionnel connecté)
     */
    public function registerUsager(Request $request): JsonResponse
    {
        // Validation des données d'entrée pour l'utilisateur et le véhicule
        $validator = Validator::make($request->all(), [
            // Données utilisateur
            'indicatif' => 'required|string',
			'nom' => 'required|string',
			'prenoms' => 'required|string',
            'mobile' => 'required|numeric|unique:users',
            'is_whatsapp' => 'required|numeric',

            // Données véhicule
            'matricule' => 'required|string|unique:vehicules',
            'carte_grise' => 'required|string|unique:vehicules',
            'photos' => 'nullable|array|size:4', // Vérifie que 4 fichiers sont fournis (nullable pour test)
            'photos.*' => 'file|image|max:25048', // Chaque fichier doit être une image de max 2 MB
            'type_de_vehicule_id' => 'required|exists:type_de_vehicules,id',
            'marque_id' => 'required|exists:marques,id',
            'type_de_carburant_id' => 'required|exists:type_de_carburants,id',
            'couleur' => 'required|string|max:50',
            'modele' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Utilisation d'une transaction pour garantir l'intégrité des données
        DB::beginTransaction();
        try {

			$rawPassword = strval(random_int(100000, 999999));

			$professionnel = auth('professionnels')->user();

            if (!$professionnel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $etablissement = Etablissement::where('professionnel_id', $professionnel->id)->first();
            if (!$etablissement) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Etablissement non trouvé.'
                ], 401);
            }

            // Création de l'utilisateur
            $user = new User();
            $user->uuid = (string) Str::uuid();
            $user->indicatif = $request->indicatif;
            $user->mobile = $request->mobile;
            $user->nom = $request->nom;
            $user->prenoms = $request->prenoms;
            $user->password = bcrypt($rawPassword); // Hash sécurisé du mot de passe
			$user->is_whatsapp = $request->is_whatsapp;
			$user->etablissement_id = $etablissement->id;
			$user->professionnel_id = $professionnel->id;

            $user->save();

            // Création automatique du véhicule pour l'utilisateur
            $vehicule = new Vehicule();
            $vehicule->matricule = $request->matricule;
            $vehicule->carte_grise = $request->carte_grise;
            $vehicule->type_de_vehicule_id = $request->type_de_vehicule_id;
            $vehicule->marque_id = $request->marque_id;
            $vehicule->type_de_carburant_id = $request->type_de_carburant_id;
            $vehicule->couleur = $request->couleur;
            $vehicule->modele = $request->modele;
            $vehicule->user_id = $user->id;
            $vehicule->provenance = 'etablissement';
            $vehicule->provenance_by = 4;
            $vehicule->created_by = $user->id;

            // Sauvegarde des photos du véhicule
            if ($request->hasFile('photos')) {
                $photosPaths = [];

                foreach ($request->file('photos') as $photo) {
                    // Générer un nom unique pour chaque photo
                    $photoName = 'vehicule-' . time() . '-' . uniqid() . '.' . $photo->getClientOriginalExtension();

                    // Déplacer la photo dans le répertoire 'public/vehicules/photos'
                    $photo->move(public_path('vehicules/photos'), $photoName);

                    // Ajouter le chemin de la photo au tableau
                    $photosPaths[] = 'vehicules/photos/' . $photoName;
                }

                // Liaison des chemins des photos avec le véhicule (stocké en JSON)
                $vehicule->photos = json_encode($photosPaths);
            }

            $vehicule->save();

            // Commit de la transaction
            DB::commit();

			$mobileWithIndicatif = $request->indicatif . $request->mobile;
			$password = $rawPassword;

			// Construire le message avec les informations du véhicule
			$message = strtoupper(
				"Votre compte a ete cree avec succes\n" .
				"Voici vos identifiants de connexion :\n" .
				"Numero de telephone : $mobileWithIndicatif\n" .
				"Mot de passe : $password\n" .
				"Votre vehicule a ete enregistre :\n" .
				"Matricule : " . $request->matricule . "\n" .
				"Carte grise : " . $request->carte_grise
			);


			// Envoyer le SMS
            $smsResponse = $this->smsService->send($message, $mobileWithIndicatif);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur et véhicule enregistrés avec succès.',
                'user' => $user,
                'vehicule' => $vehicule,
            ], 201); // Utilisation du code HTTP 201 pour "Created"

        } catch (\Exception $e) {
            // Rollback de la transaction en cas d'erreur
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de l'utilisateur et du véhicule.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Liste des usagers créés par l'établissement du professionnel connecté
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

        $users = User::where('etablissement_id', $etablissement->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function type_de_vehicules(): JsonResponse
    {
        $type_de_vehicules = TypeDeVehicule::all();

        return response()->json($type_de_vehicules);
    }

    public function marques(): JsonResponse
    {
        $marques = Marque::all();

        return response()->json($marques);
    }

    public function type_de_carburants(): JsonResponse
    {
        $type_de_carburants = TypeDeCarburant::all();

        return response()->json($type_de_carburants);
    }

    public function type_alerts(): JsonResponse
    {
        $type_alerts = TypeAlert::all();

        return response()->json($type_alerts);
    }
}
