<?php

use App\Http\Controllers\Api\V1\CalculationController;
use App\Http\Controllers\Api\V1\DesignController;
use App\Http\Controllers\Api\V1\InputSchemaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('input-schema', InputSchemaController::class)->name('input-schema');
    Route::post('calculate', CalculationController::class)->name('calculate');
    Route::apiResource('designs', DesignController::class);
});
