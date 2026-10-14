<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});
Route::get('v1/ping', function () { return response()->json(['ping' => 'pong']); });
