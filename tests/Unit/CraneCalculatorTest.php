<?php

namespace Tests\Unit;

use App\Services\Crane\CraneCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CraneCalculatorTest extends TestCase
{
    /**
     * @return array{capacity_t: int|float, span_mm: int, lifting_height_mm: int, rail_height_mm: int}
     */
    private function referenceInputs(): array
    {
        return ['capacity_t' => 10, 'span_mm' => 25000, 'lifting_height_mm' => 7900, 'rail_height_mm' => 8321];
    }

    private function value(array $result, string $key): int|float
    {
        return $result['dimensions'][$key]['value'];
    }

    public function test_reference_crane_reproduces_the_drawing_numbers(): void
    {
        $result = (new CraneCalculator)->calculate($this->referenceInputs());

        $this->assertSame(25270, $this->value($result, 'overall_length'));
        $this->assertSame(620, $this->value($result, 'hook_headroom'));
        $this->assertSame('reference', $result['dimensions']['hook_headroom']['source']);
        $this->assertSame(920, $this->value($result, 'hook_approach_left'));
        $this->assertSame(1150, $this->value($result, 'hook_approach_right'));
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_reference_crane_estimates_stay_close_to_the_drawing(): void
    {
        $result = (new CraneCalculator)->calculate($this->referenceInputs());

        $this->assertEqualsWithDelta(1369, $this->value($result, 'crane_top_above_rail'), 1369 * 0.03);
        $this->assertEqualsWithDelta(3800, $this->value($result, 'wheelbase'), 3800 * 0.1);
        $this->assertGreaterThanOrEqual(7900, $this->value($result, 'max_lifting_height'));
    }

    public function test_every_dimension_declares_a_known_source_and_a_basis(): void
    {
        $result = (new CraneCalculator)->calculate($this->referenceInputs());

        foreach ($result['dimensions'] as $key => $dimension) {
            $this->assertContains($dimension['source'], ['input', 'reference', 'derived', 'estimate'], $key);
            $this->assertNotSame('', $dimension['basis'], $key);
        }
    }

    public function test_lifting_height_above_hoist_is_an_error(): void
    {
        $result = (new CraneCalculator)->calculate([...$this->referenceInputs(), 'lifting_height_mm' => 8000]);

        $this->assertArrayHasKey('lifting_height_mm', $result['errors']);
    }

    public function test_building_lower_than_crane_is_an_error(): void
    {
        $result = (new CraneCalculator)->calculate([...$this->referenceInputs(), 'building_height_mm' => 9000]);

        $this->assertArrayHasKey('building_height_mm', $result['errors']);
    }

    public function test_short_span_uses_minimum_girder_depth_and_wheelbase(): void
    {
        $result = (new CraneCalculator)->calculate([...$this->referenceInputs(), 'span_mm' => 5000]);

        $this->assertSame(CraneCalculator::GIRDER_DEPTH_MIN, $this->value($result, 'girder_depth'));
        $this->assertSame(CraneCalculator::WHEELBASE_MIN, $this->value($result, 'wheelbase'));
    }

    /**
     * @return array<string, array{0: float, 1: int}>
     */
    public static function headroomCases(): array
    {
        return [
            '1 t' => [1, 450],
            '2 t boundary' => [2, 450],
            '3 t' => [3, 520],
            '10 t' => [10, 620],
            '12.5 t' => [12.5, 720],
            '20 t' => [20, 800],
        ];
    }

    #[DataProvider('headroomCases')]
    public function test_hook_headroom_steps_with_capacity(float $capacity, int $expected): void
    {
        $result = (new CraneCalculator)->calculate([...$this->referenceInputs(), 'capacity_t' => $capacity, 'lifting_height_mm' => 3000]);

        $this->assertSame($expected, $this->value($result, 'hook_headroom'));
    }

    public function test_far_from_reference_inputs_warn_about_extrapolation(): void
    {
        $result = (new CraneCalculator)->calculate([...$this->referenceInputs(), 'capacity_t' => 2, 'span_mm' => 8000]);

        $this->assertNotEmpty($result['warnings']);
    }
}
