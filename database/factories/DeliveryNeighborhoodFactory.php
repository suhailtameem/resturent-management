<?php

namespace Database\Factories;

use App\Models\DeliveryCity;
use App\Models\DeliveryNeighborhood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNeighborhood>
 */
class DeliveryNeighborhoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_city_id' => DeliveryCity::factory(),
            'name' => fake()->streetName(),
            'delivery_fee' => fake()->randomFloat(2, 0, 100),
            'minimum_order_amount' => fake()->randomFloat(2, 0, 500),
            'is_active' => true,
        ];
    }
}
