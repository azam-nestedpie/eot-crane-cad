<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capacity_t',
        'span_mm',
        'lifting_height_mm',
        'rail_height_mm',
        'building_height_mm',
        'duty_group',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity_t' => 'float',
            'span_mm' => 'integer',
            'lifting_height_mm' => 'integer',
            'rail_height_mm' => 'integer',
            'building_height_mm' => 'integer',
        ];
    }

    /**
     * The subset of attributes the crane calculator needs.
     *
     * @return array<string, int|float|string|null>
     */
    public function craneInputs(): array
    {
        return $this->only([
            'capacity_t',
            'span_mm',
            'lifting_height_mm',
            'rail_height_mm',
            'building_height_mm',
            'duty_group',
        ]);
    }
}
