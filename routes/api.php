<?php

use App\Http\Controllers\Api\roomSearch;
use App\Http\Controllers\Api\CamerehotelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Protected API routes that require secret
Route::middleware('api.secret')->group(function () {
    Route::post('/rooms/search-combinations', [roomSearch::class, 'searchAvailableRoomCombinations']);

    // Camerehotel API routes
    Route::apiResource('camerehotel', CamerehotelController::class);
    Route::get('/camerehotel/hotel/{hotelId}', [CamerehotelController::class, 'getRoomsByHotel']);
    Route::get('/camerehotel/type/{type}', [CamerehotelController::class, 'getRoomsByType']);
    Route::get('/camerehotel/hotel/{hotelId}/floor/{floor}', [CamerehotelController::class, 'getRoomsByFloor']);
    Route::post('/camerehotel/search-capacity', [CamerehotelController::class, 'searchByCapacity']);
    Route::get('/camerehotel/{id}/statistics', [CamerehotelController::class, 'getRoomStatistics']);
    Route::get('/camerehotel/virtual/list', [CamerehotelController::class, 'getVirtualRooms']);
    Route::get('/camerehotel/baby-beds/list', [CamerehotelController::class, 'getRoomsWithBabyBeds']);
});
