<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SP-#####'),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'unit' => 'pcs',
            'purchase_price' => '10000.00',
            'selling_price' => '15000.00',
            'stock' => 0,
            'min_stock' => 5,
        ];
    }
}
