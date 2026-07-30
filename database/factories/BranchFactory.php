<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_code' => 'BR' . $this->faker->unique()->numberBetween(100, 999),
            'branch_name' => $this->faker->city() . ' ' . $this->faker->randomElement(['Main', 'East', 'West', 'Downtown', 'Metro']) . ' Branch',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'manager_id' => null, // Left null to avoid infinite loop with users seeder; we seed it afterwards
            'total_employees' => $this->faker->numberBetween(3, 30),
            'status' => $this->faker->randomElement(['active', 'inactive', 'under_renovation']),
        ];
    }
}
