<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmailLog>
 */
class EmailLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'subject' => fake()->sentence(4),
            'email_type' => fake()->randomElement(['designation', 'certificate', 'notification', 'system']),
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }
}
