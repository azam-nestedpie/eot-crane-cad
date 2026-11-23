<?php

namespace App\Http\Requests;

use App\Enums\DutyGroup;
use App\Services\Crane\CraneCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Server-side validation for the crane inputs.
 *
 * Two layers: simple per-field ranges first, then physical cross-field
 * rules from the calculator (e.g. the hook cannot be lifted above the
 * hoist). The cross-field layer only runs if every field is valid.
 */
class CraneInputRequest extends FormRequest
{
    /** @var list<string> */
    public const CRANE_FIELDS = [
        'capacity_t',
        'span_mm',
        'lifting_height_mm',
        'rail_height_mm',
        'building_height_mm',
        'duty_group',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $limits = CraneCalculator::LIMITS;

        return [
            'capacity_t' => ['required', 'numeric', 'between:'.implode(',', $limits['capacity_t']), 'multiple_of:0.5'],
            'span_mm' => ['required', 'integer', 'between:'.implode(',', $limits['span_mm'])],
            'lifting_height_mm' => ['required', 'integer', 'between:'.implode(',', $limits['lifting_height_mm'])],
            'rail_height_mm' => ['required', 'integer', 'between:'.implode(',', $limits['rail_height_mm'])],
            'building_height_mm' => ['nullable', 'integer', 'between:'.implode(',', $limits['building_height_mm'])],
            'duty_group' => ['required', Rule::enum(DutyGroup::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'capacity_t.multiple_of' => 'Capacity must be in steps of 0.5 t.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $result = app(CraneCalculator::class)->calculate($this->craneInputs());

                foreach ($result['errors'] as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function craneInputs(): array
    {
        return $this->only(self::CRANE_FIELDS);
    }
}
