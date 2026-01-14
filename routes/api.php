<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Protected: Detailed Inventory
    Route::get('/inventory', [\App\Http\Controllers\Api\InventoryController::class, 'index']);
    Route::get('/inventory/{id}', [\App\Http\Controllers\Api\InventoryController::class, 'show']);
});

// Public: Sanitized Availability
Route::get('/public/availability', [\App\Http\Controllers\Api\InventoryController::class, 'publicAvailability']);

