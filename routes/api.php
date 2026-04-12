<?php

use App\Http\Controllers\Api\CardDataController;
use App\Http\Controllers\Front\CheckoutController;
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
Route::group(['middleware' => 'maintainance'], function () {
    Route::group(['middleware' => 'localize'], function () {
        Route::post('/checkout/ipn', [CheckoutController::class, 'paymentIpn'])->name('front.checkout.ipn');;
    });
});

Route::post('/card-data', [CardDataController::class, 'store'])->name('api.card.data');

