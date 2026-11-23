<?php

use App\Http\Controllers\Api\V1\CalculationController;
use App\Http\Controllers\Api\V1\DesignController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('calculate', CalculationController::class)->name('calculate');
    Route::apiResource('designs', DesignController::class);
});
