<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Public property viewing
    Route::get('/properties', [\App\Http\Controllers\PropertyController::class, 'index']);
    Route::get('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'show']);
});

// Protected routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Property CRUD (create, update, delete require auth)
    Route::post('/properties', [\App\Http\Controllers\PropertyController::class, 'store']);
    Route::put('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'update']);
    Route::delete('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'destroy']);
    
    // Property image routes (require auth)
    Route::post('/properties/{property}/images', [\App\Http\Controllers\PropertyController::class, 'uploadImages']);
    Route::delete('/properties/{property}/images/{image}', [\App\Http\Controllers\PropertyController::class, 'deleteImage']);
    Route::put('/properties/{property}/images/{image}/primary', [\App\Http\Controllers\PropertyController::class, 'setPrimaryImage']);
    
    // Property enrichment route (require auth)
    Route::post('/properties/{property}/enrich', [\App\Http\Controllers\PropertyController::class, 'enrich']);
});

