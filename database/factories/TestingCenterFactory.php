<?php

namespace Database\Factories;

use App\Models\FieldOffice;
use App\Models\TestingCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TestingCenter>
 */
class TestingCenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' City',
            'is_active' => true,
        ];
    }

    /**
     * Link the testing center to a field office (many-to-many). May be chained
     * multiple times to place one center under several offices.
     */
    public function forFieldOffice(FieldOffice|int $office): static
    {
        return $this->afterCreating(function (TestingCenter $center) use ($office) {
            $center->fieldOffices()->syncWithoutDetaching([$office instanceof FieldOffice ? $office->id : $office]);
        });
    }
}
