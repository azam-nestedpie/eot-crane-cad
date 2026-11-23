<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DutyGroup;
use App\Http\Controllers\Controller;
use App\Services\Crane\CraneCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Describes the accepted inputs so the UI builds its sliders and hints from
 * the same limits the server validates against (no copied numbers).
 */
class InputSchemaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $meta = [
            'capacity_t' => ['label' => 'Rated capacity', 'unit' => 't', 'step' => 0.5, 'required' => true],
            'span_mm' => ['label' => 'Span', 'unit' => 'mm', 'step' => 100, 'required' => true],
            'rail_height_mm' => ['label' => 'Rail height', 'unit' => 'mm', 'step' => 10, 'required' => true],
            'lifting_height_mm' => ['label' => 'Lifting height', 'unit' => 'mm', 'step' => 10, 'required' => true],
            'building_height_mm' => ['label' => 'Building clear height', 'unit' => 'mm', 'step' => 10, 'required' => false],
        ];

        $fields = [];
        foreach ($meta as $key => $field) {
            [$min, $max] = CraneCalculator::LIMITS[$key];
            $fields[$key] = [...$field, 'min' => $min, 'max' => $max];
        }

        return response()->json([
            'data' => [
                'fields' => $fields,
                'duty_groups' => array_column(DutyGroup::cases(), 'value'),
            ],
        ]);
    }
}
