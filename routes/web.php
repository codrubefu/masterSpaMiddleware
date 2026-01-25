<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomSearchTestController;
use App\Http\Controllers\EnvConfigController;
use App\Http\Controllers\VoucherPreviewController;

Route::get('/voucher-preview', [VoucherPreviewController::class, 'show'])->name('voucher.preview');
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/env-config', [EnvConfigController::class, 'index'])->name('env-config.index');

Route::get('/room-search-test', [RoomSearchTestController::class, 'showForm'])->name('room-search-test.form');
Route::post('/room-search-test', [RoomSearchTestController::class, 'submitForm'])->name('room-search-test.submit');
