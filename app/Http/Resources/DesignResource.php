<?php

namespace App\Http\Resources;

use App\Models\Design;
use App\Services\Crane\CraneCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Design
 */
class DesignResource extends JsonResource
{
    private bool $withCalculation = false;

    /**
     * Include the recalculated dimensions (used for single-design responses,
     * left out of the list to keep it light).
     */
    public function withCalculation(): static
    {
        $this->withCalculation = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity_t' => $this->capacity_t,
            'span_mm' => $this->span_mm,
            'lifting_height_mm' => $this->lifting_height_mm,
            'rail_height_mm' => $this->rail_height_mm,
            'building_height_mm' => $this->building_height_mm,
            'duty_group' => $this->duty_group,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'calculation' => $this->when(
                $this->withCalculation,
                fn (): array => app(CraneCalculator::class)->calculate($this->resource->craneInputs()),
            ),
        ];
    }
}
