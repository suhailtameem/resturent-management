<?php

namespace Database\Factories;

use App\Models\DeliveryCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryCity>
 */
class DeliveryCityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'service_status' => fake()->randomElement(['active', 'coming_soon', 'preorder_only']),
        ];
    }
}
