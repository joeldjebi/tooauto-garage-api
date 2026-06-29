<?php

use App\Http\Controllers\Api\AbonnementProController;
use App\Http\Controllers\Api\AnnonceController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForfaitProController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\EtablissementController;
use App\Http\Controllers\Api\QrcodeController;
use App\Http\Controllers\Api\UsagerController;
use App\Http\Controllers\Api\VidangeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('villes', [AuthController::class, 'villes']);
    Route::get('communes', [AuthController::class, 'communes']);
    Route::get('type-de-prestations', [AuthController::class, 'typeDePrestations']);
    Route::get('categorie-services', [AuthController::class, 'categorieServices']);
Route::post('update-etablissement-medias/{id}', [EtablissementController::class, 'updateMedias'])
    ->middleware('auth:professionnels');
    Route::middleware('auth:professionnels')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'update']);
        Route::patch('me', [AuthController::class, 'update']);
        Route::put('password', [AuthController::class, 'updatePassword']);
        Route::patch('password', [AuthController::class, 'updatePassword']);
        Route::post('logout', [AuthController::class, 'logout']);
        // Rafraîchir le token : POST /api/auth/refresh avec header Authorization: Bearer {token}
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::prefix('etablissements')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [EtablissementController::class, 'show']);
    Route::post('/', [EtablissementController::class, 'createOrUpdate']);
    Route::put('/', [EtablissementController::class, 'createOrUpdate']);
    Route::get('/{id}/share-localisations', [EtablissementController::class, 'share_localisations']);
    Route::delete('/share-localisations/{share_localisation_id}', [EtablissementController::class, 'share_localisation_delete']);
	        // Route pour mettre à jour le logo et la cover d'un établissement
        Route::post('/update-etablissement-medias/{id}', [EtablissementController::class, 'updateMedias']);
		
});

Route::prefix('qrcodes')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [QrcodeController::class, 'index']);
    Route::post('assign-scan-lavage', [QrcodeController::class, 'assignByScanLavage']);
});

Route::prefix('vidanges')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [VidangeController::class, 'index']);
    Route::post('/', [VidangeController::class, 'store']);
    Route::get('last-by-matricule', [VidangeController::class, 'getLastVidangeByMatricule']);
    Route::get('vehicule-by-matricule', [VidangeController::class, 'getVehiculeByMatricule']);
});

Route::prefix('usagers')->middleware('auth:professionnels')->group(function () {
    Route::get('marques', [UsagerController::class, 'marques']);
    Route::get('type-de-vehicules', [UsagerController::class, 'type_de_vehicules']);
    Route::get('type-de-carburants', [UsagerController::class, 'type_de_carburants']);
    Route::get('type-alerts', [UsagerController::class, 'type_alerts']);
    Route::get('/', [UsagerController::class, 'index']);
    Route::post('/', [UsagerController::class, 'registerUsager']);
});

Route::prefix('annonces')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [AnnonceController::class, 'index']);
    Route::post('hide', [AnnonceController::class, 'hide']);
});

Route::prefix('articles')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::post('/', [ArticleController::class, 'store']);
    Route::get('/{id}', [ArticleController::class, 'show']);
    Route::put('/{id}', [ArticleController::class, 'update']);
    Route::patch('/{id}', [ArticleController::class, 'update']);
    Route::delete('/{id}', [ArticleController::class, 'destroy']);
});

Route::prefix('abonnements')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [AbonnementProController::class, 'index']);
});

Route::prefix('forfaits')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [ForfaitProController::class, 'index']);
});

Route::prefix('promotions')->middleware('auth:professionnels')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::post('/', [PromotionController::class, 'store']);
    Route::get('/{id}', [PromotionController::class, 'show']);
    Route::put('/{id}', [PromotionController::class, 'update']);
    Route::patch('/{id}', [PromotionController::class, 'update']);
    Route::delete('/{id}', [PromotionController::class, 'destroy']);
});
