<?php

namespace Database\Seeders;

use App\Models\Design;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * One example design: the crane from the assignment's reference drawing.
     */
    public function run(): void
    {
        $name = 'Reference drawing crane (10 t, 25 m)';

        Design::query()->firstOrCreate(['name' => $name], Design::factory()->raw([
            'name' => $name,
            'notes' => 'Inputs copied from the sample drawing in the assignment PDF.',
        ]));
    }
}
