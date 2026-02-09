<?php

use App\Http\Controllers\Api\roomSearch;
use App\Http\Controllers\Api\CamerehotelController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OrderHotelController;
use App\Http\Controllers\Api\OrderSpaController;
use App\Http\Controllers\Api\RezervarehotelController;
use App\Http\Controllers\TestEmailController;
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

    // Camerehotel API routes - specific routes before resource routes
    Route::get('/camerehotel/grouped-by-type', [CamerehotelController::class, 'getRoomsGroupedByType']);
    Route::get('/camerehotel/virtual/list', [CamerehotelController::class, 'getVirtualRooms']);
    Route::get('/camerehotel/baby-beds/list', [CamerehotelController::class, 'getRoomsWithBabyBeds']);
    Route::get('/camerehotel/hotel/{hotelId}', [CamerehotelController::class, 'getRoomsByHotel']);
    Route::get('/camerehotel/type/{type}', [CamerehotelController::class, 'getRoomsByType']);
    Route::get('/camerehotel/hotel/{hotelId}/floor/{floor}', [CamerehotelController::class, 'getRoomsByFloor']);
    Route::post('/camerehotel/search-capacity', [CamerehotelController::class, 'searchByCapacity']);
    Route::get('/camerehotel/{id}/statistics', [CamerehotelController::class, 'getRoomStatistics']);

    // Standard CRUD routes
    Route::apiResource('camerehotel', CamerehotelController::class);

    // Client API routes
    Route::apiResource('clients', ClientController::class);
    Route::post('/clients/search', [ClientController::class, 'search']);
    Route::get('/clients/vip/list', [ClientController::class, 'getVipClients']);
    Route::patch('/clients/{id}/vip-status', [ClientController::class, 'updateVipStatus']);
    Route::get('/clients/{id}/reservations', [ClientController::class, 'getReservations']);
    Route::get('/clients/{id}/statistics', [ClientController::class, 'getStatistics']);
    Route::get('/clients/employees/list', [ClientController::class, 'getEmployees']);
    Route::patch('/clients/{id}/employee-status', [ClientController::class, 'updateEmployeeStatus']);
    Route::get('/clients/company/{companyId}', [ClientController::class, 'getCompanyClients']);

    // Rezervarehotel API routes
    Route::apiResource('reservations', RezervarehotelController::class);
    Route::patch('/reservations/{id}/checkin', [RezervarehotelController::class, 'checkIn']);
    Route::patch('/reservations/{id}/checkout', [RezervarehotelController::class, 'checkOut']);
    Route::patch('/reservations/{id}/payment', [RezervarehotelController::class, 'updatePayment']);
    Route::patch('/reservations/{id}/cancel', [RezervarehotelController::class, 'cancel']);
    Route::get('/reservations/room/{roomNumber}', [RezervarehotelController::class, 'getRoomReservations']);
    Route::get('/reservations/statistics/overview', [RezervarehotelController::class, 'getStatistics']);
    Route::get('/reservations/arrivals/today', [RezervarehotelController::class, 'getTodaysArrivals']);
    Route::get('/reservations/departures/today', [RezervarehotelController::class, 'getTodaysDepartures']);
 
    Route::post('/order', [OrderHotelController::class, 'save']); 
    Route::post('/order/spa', [OrderSpaController::class, 'save']);

    // Test Email routes
    Route::post('/test-email/send', [TestEmailController::class, 'sendTestEmail']);
    Route::post('/test-email/send/{email}', [TestEmailController::class, 'sendTestEmailToCustom']);

        // Genprod CRUD routes
        Route::apiResource('genprod', \App\Http\Controllers\GenprodController::class);

            // Genprod SPA filter
            Route::get('genprod/spa/only', [\App\Http\Controllers\GenprodController::class, 'onlySpa']);


});
            Route::get('/spa/preview', [\App\Http\Controllers\VoucherPreviewController::class, 'show']);
