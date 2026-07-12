<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Letterhead>
 */
class LetterheadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Letterhead',
            'file_path' => 'letterheads/'.fake()->uuid().'.png',
            'is_active' => false,
        ];
    }
}
