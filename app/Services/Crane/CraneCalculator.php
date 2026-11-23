<?php

namespace App\Services\Crane;

/**
 * Turns user inputs for a single-girder, top-running EOT crane into the
 * dimensions shown on the drawing.
 *
 * Every dimension carries a "source" so nothing pretends to be more exact
 * than it is:
 *  - input:     typed by the user.
 *  - reference: read directly off the reference drawing
 *               (10 t, 25 m span, "PRELIMINARY DRAWING / OFFER ONLY").
 *  - derived:   pure geometry from inputs/reference values only.
 *  - estimate:  inferred or scaled by me from a single drawing, or computed
 *               from such a value. Not verified.
 *
 * All lengths are millimetres, capacity is tonnes.
 */
class CraneCalculator
{
    /** @var array<string, array{0: int|float, 1: int|float}> Accepted input ranges [min, max]. */
    public const LIMITS = [
        'capacity_t' => [1, 20],
        'span_mm' => [5000, 30000],
        'lifting_height_mm' => [2500, 20000],
        'rail_height_mm' => [3500, 22000],
        'building_height_mm' => [4000, 30000],
    ];

    /** Reference: 25270 overall − 25000 span = 270, assumed split evenly. */
    public const END_OVERHANG = 135;

    /** Reference: "Min.235" crane end to wall, both sides. */
    public const WALL_CLEARANCE = 235;

    /** Reference: "200" top of crane to roof. */
    public const ROOF_CLEARANCE = 200;

    /** Estimate: 7900 + 620 − 8321 = 199, i.e. girder underside ≈ 200 above rail top. */
    public const GIRDER_BOTTOM_ABOVE_RAIL = 200;

    /** Estimate: girder depth ≈ span / 21 (1369 − 200 ≈ 1170 at 25 m in the reference). */
    public const GIRDER_DEPTH_RATIO = 21;

    public const GIRDER_DEPTH_MIN = 400;

    /** Reference: "Min.920" left and "Min.1150" right hook approach. */
    public const HOOK_APPROACH_LEFT = 920;

    public const HOOK_APPROACH_RIGHT = 1150;

    /** Estimate: wheelbase ≥ span / 7 (rule of thumb against skewing). */
    public const SPAN_TO_WHEELBASE_MAX = 7;

    public const WHEELBASE_MIN = 2000;

    /** Estimate: 4310 end carriage length − 3800 wheelbase = 510, assumed constant. */
    public const END_CARRIAGE_EXTRA = 510;

    /** Reference: Detail Z, wheel Ø250. */
    public const WHEEL_DIAMETER = 250;

    /** Reference: pendant control hangs to 1000 above floor. */
    public const PENDANT_ABOVE_FLOOR = 1000;

    /**
     * Estimate: hook highest position to girder underside, by capacity.
     * Only the 10 t value (620) is read from the reference; the rest are my
     * guesses that the hoist grows with capacity. Real values come from the
     * hoist manufacturer's catalogue.
     *
     * @var array<int, int> max capacity (t) => headroom (mm)
     */
    public const HOOK_HEADROOM_BY_CAPACITY = [
        2 => 450,
        5 => 520,
        10 => 620,
        16 => 720,
        20 => 800,
    ];

    /**
     * @param  array{capacity_t: int|float, span_mm: int, lifting_height_mm: int, rail_height_mm: int, building_height_mm?: int|null, duty_group?: string|null}  $inputs
     * @return array{
     *     dimensions: array<string, array{label: string, value: int|float, unit: string, source: string, basis: string}>,
     *     errors: array<string, string>,
     *     warnings: list<string>
     * }
     */
    public function calculate(array $inputs): array
    {
        $capacity = (float) $inputs['capacity_t'];
        $span = (int) $inputs['span_mm'];
        $liftingHeight = (int) $inputs['lifting_height_mm'];
        $railHeight = (int) $inputs['rail_height_mm'];
        $buildingHeight = isset($inputs['building_height_mm']) ? (int) $inputs['building_height_mm'] : null;

        $girderDepth = max(self::GIRDER_DEPTH_MIN, $this->roundTo($span / self::GIRDER_DEPTH_RATIO, 10));
        $craneTopAboveRail = self::GIRDER_BOTTOM_ABOVE_RAIL + $girderDepth;
        $hookHeadroom = $this->hookHeadroom($capacity);
        $maxLiftingHeight = $railHeight + self::GIRDER_BOTTOM_ABOVE_RAIL - $hookHeadroom;
        $wheelbase = max(self::WHEELBASE_MIN, $this->roundUpTo($span / self::SPAN_TO_WHEELBASE_MAX, 100));
        $overallLength = $span + 2 * self::END_OVERHANG;
        $minBuildingHeight = $railHeight + $craneTopAboveRail + self::ROOF_CLEARANCE;

        $d = [];
        $d['capacity'] = $this->dim('Rated capacity', $capacity, 't', 'input', 'User input.');
        $d['span'] = $this->dim('Span (rail centre to rail centre)', $span, 'mm', 'input', 'User input.');
        $d['lifting_height'] = $this->dim('Lifting height (floor to hook, highest)', $liftingHeight, 'mm', 'input', 'User input.');
        $d['rail_height'] = $this->dim('Rail height (floor to top of rail)', $railHeight, 'mm', 'input', 'User input.');
        if ($buildingHeight !== null) {
            $d['building_height'] = $this->dim('Building clear height (floor to roof)', $buildingHeight, 'mm', 'input', 'User input (optional).');
        }

        $d['end_overhang'] = $this->dim('Crane end beyond rail centre', self::END_OVERHANG, 'mm', 'reference',
            'Reference: overall 25270 − span 25000 = 270, split evenly (assumed symmetric).');
        $d['overall_length'] = $this->dim('Crane overall length', $overallLength, 'mm', 'derived',
            'Span + 2 × end overhang.');
        $d['wall_clearance'] = $this->dim('Min. clearance crane end to wall', self::WALL_CLEARANCE, 'mm', 'reference',
            'Reference: "Min.235" both sides. Kept constant for all sizes.');
        $d['min_building_width'] = $this->dim('Min. building width (wall to wall)', $overallLength + 2 * self::WALL_CLEARANCE, 'mm', 'derived',
            'Overall length + 2 × wall clearance. Note: reference plan shows 25500, which does not match 25740; unresolved.');

        $d['girder_bottom_above_rail'] = $this->dim('Girder underside above rail top', self::GIRDER_BOTTOM_ABOVE_RAIL, 'mm', 'estimate',
            'Inferred from reference: 7900 + 620 − 8321 ≈ 200. Assumed constant for all sizes.');
        $d['girder_depth'] = $this->dim('Girder depth', $girderDepth, 'mm', 'estimate',
            'Span ÷ 21, min 400. Fitted to one point (≈1170 at 25 m). Real depth needs a strength/deflection calc and depends on capacity too.');
        $d['crane_top_above_rail'] = $this->dim('Crane top above rail top', $craneTopAboveRail, 'mm', 'estimate',
            'Derived from two estimates: girder underside above rail + girder depth. Reference shows 1369 at 25 m; this model gives 1390.');
        $d['roof_clearance'] = $this->dim('Min. clearance crane top to roof', self::ROOF_CLEARANCE, 'mm', 'reference',
            'Reference: "200" above the crane.');
        $d['min_building_height'] = $this->dim('Min. building clear height', $minBuildingHeight, 'mm', 'estimate',
            'Rail height + crane top above rail + roof clearance. Inherits the girder depth estimate.');

        $d['hook_headroom'] = $this->dim('Hook (highest) to girder underside', $hookHeadroom, 'mm', $this->isReferenceCapacity($capacity) ? 'reference' : 'estimate',
            'Reference shows 620 for the 10 t hoist. Other capacities use my guessed table (2t:450, 5t:520, 10t:620, 16t:720, 20t:800). Should come from the hoist catalogue.');
        $d['max_lifting_height'] = $this->dim('Max. possible lifting height', $maxLiftingHeight, 'mm', 'estimate',
            'Rail height + girder underside above rail − hook headroom. Inherits those estimates.');
        $d['hook_approach_left'] = $this->dim('Min. hook approach, left (from rail centre)', self::HOOK_APPROACH_LEFT, 'mm', 'reference',
            'Reference: "Min.920". Assumed measured from rail centre. Depends on the actual hoist model.');
        $d['hook_approach_right'] = $this->dim('Min. hook approach, right (from rail centre)', self::HOOK_APPROACH_RIGHT, 'mm', 'reference',
            'Reference: "Min.1150". Assumed measured from rail centre. Depends on the actual hoist model.');
        $d['pendant_above_floor'] = $this->dim('Pendant control above floor', self::PENDANT_ABOVE_FLOOR, 'mm', 'reference',
            'Reference: "1000" from floor to the pendant box.');

        $d['wheelbase'] = $this->dim('End carriage wheelbase', $wheelbase, 'mm', 'estimate',
            'Span ÷ 7 rounded up to 100, min 2000. Rule of thumb (span/wheelbase ≤ 7) against skewing; not checked against a standard. Reference: 25000/3800 = 6.6, consistent.');
        $d['end_carriage_length'] = $this->dim('End carriage overall length', $wheelbase + self::END_CARRIAGE_EXTRA, 'mm', 'estimate',
            'Wheelbase + 510 (reference: 4310 − 3800). Assumed constant.');
        $d['wheel_diameter'] = $this->dim('Travel wheel diameter', self::WHEEL_DIAMETER, 'mm', 'reference',
            'Reference Detail Z: Ø250. Not scaled with load; real size depends on wheel load.');

        return [
            'dimensions' => $d,
            'errors' => $this->errors($liftingHeight, $maxLiftingHeight, $buildingHeight, $minBuildingHeight),
            'warnings' => $this->warnings($capacity, $span),
        ];
    }

    /**
     * Cross-field rules that make a design physically impossible.
     *
     * @return array<string, string> field => message
     */
    private function errors(int $liftingHeight, int $maxLiftingHeight, ?int $buildingHeight, int $minBuildingHeight): array
    {
        $errors = [];

        if ($liftingHeight > $maxLiftingHeight) {
            $errors['lifting_height_mm'] = "Lifting height {$liftingHeight} mm is above the max {$maxLiftingHeight} mm for this rail height and capacity (hook cannot go higher than the hoist under the girder). Lower the lifting height or raise the rail.";
        }

        if ($buildingHeight !== null && $buildingHeight < $minBuildingHeight) {
            $errors['building_height_mm'] = "Building clear height {$buildingHeight} mm is below the min {$minBuildingHeight} mm needed (rail height + crane height + 200 roof clearance).";
        }

        return $errors;
    }

    /**
     * Soft warnings: the design is allowed but the user should know.
     *
     * @return list<string>
     */
    private function warnings(float $capacity, int $span): array
    {
        $warnings = [];

        if (abs($capacity - 10) > 5 || abs($span - 25000) > 10000) {
            $warnings[] = 'Inputs are far from the single reference crane (10 t, 25 m). Estimated dimensions are extrapolated and less reliable.';
        }

        if ($capacity > 10 && $span > 22000) {
            $warnings[] = 'Heavy load on a long span: in practice this may need a double-girder crane. Not checked by this tool.';
        }

        return $warnings;
    }

    private function hookHeadroom(float $capacity): int
    {
        foreach (self::HOOK_HEADROOM_BY_CAPACITY as $maxCapacity => $headroom) {
            if ($capacity <= $maxCapacity) {
                return $headroom;
            }
        }

        return max(self::HOOK_HEADROOM_BY_CAPACITY);
    }

    private function isReferenceCapacity(float $capacity): bool
    {
        return $capacity == 10.0;
    }

    /**
     * @return array{label: string, value: int|float, unit: string, source: string, basis: string}
     */
    private function dim(string $label, int|float $value, string $unit, string $source, string $basis): array
    {
        return compact('label', 'value', 'unit', 'source', 'basis');
    }

    private function roundTo(float $value, int $step): int
    {
        return (int) (round($value / $step) * $step);
    }

    private function roundUpTo(float $value, int $step): int
    {
        return (int) (ceil($value / $step) * $step);
    }
}
