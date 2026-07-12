<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(4),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'body_plain' => fake()->paragraph(),
            'variables' => null,
            'is_active' => true,
        ];
    }
}
