<?php

namespace Database\Factories;

use App\Models\ProductSizeTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSizeTemplate>
 */
class ProductSizeTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'size_key' => fake()->randomElement(['small', 'medium', 'large', 'jumbo', 'family']),
            'name_ar' => fake()->word(),
            'name_en' => fake()->word(),
            'piece_count' => fake()->numberBetween(1, 20),
            'portion_description_ar' => fake()->optional()->sentence(4),
            'portion_description_en' => fake()->optional()->sentence(4),
            'price' => fake()->randomFloat(2, 1, 200),
        ];
    }
}
