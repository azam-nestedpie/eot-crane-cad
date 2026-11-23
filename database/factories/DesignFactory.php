<?php

namespace Database\Factories;

use App\Enums\DutyGroup;
use App\Models\Design;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Design>
 */
class DesignFactory extends Factory
{
    /**
     * Defaults to the reference crane from the assignment drawing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'capacity_t' => 10,
            'span_mm' => 25000,
            'lifting_height_mm' => 7900,
            'rail_height_mm' => 8321,
            'building_height_mm' => null,
            'duty_group' => DutyGroup::A5->value,
            'notes' => null,
        ];
    }
}
