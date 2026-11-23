<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CraneInputRequest;
use App\Services\Crane\CraneCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Validates inputs and returns the dimensions without saving.
 * Used for the live drawing preview while the user edits.
 */
class CalculationController extends Controller
{
    public function __invoke(CraneInputRequest $request, CraneCalculator $calculator): JsonResponse
    {
        return response()->json([
            'data' => $calculator->calculate($request->craneInputs()),
        ]);
    }
}
