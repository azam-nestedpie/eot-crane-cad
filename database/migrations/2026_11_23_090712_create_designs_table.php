<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per saved crane design. Inputs are stored as real columns
     * (not a JSON blob) so they stay typed and queryable. Derived
     * dimensions are NOT stored: they are recalculated from the inputs,
     * so a change to a formula updates every design consistently.
     */
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('capacity_t', 5, 2);
            $table->unsignedInteger('span_mm');
            $table->unsignedInteger('lifting_height_mm');
            $table->unsignedInteger('rail_height_mm');
            $table->unsignedInteger('building_height_mm')->nullable();
            $table->string('duty_group', 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
