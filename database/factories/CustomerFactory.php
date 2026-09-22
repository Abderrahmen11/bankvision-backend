<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = \fake()->numberBetween(2020, 2026);

        return [
            'customer_number' => 'CUST-' . $year . '-' . \fake()->unique()->numberBetween(10000, 99999),
            'full_name' => \fake()->name(),
            'email' => \fake()->unique()->safeEmail(),
            'phone' => \fake()->phoneNumber(),
            'address' => \fake()->streetAddress(),
            'city' => \fake()->city(),
            'customer_type' => \fake()->randomElement(['premium', 'regular', 'business']),
            'kyc_status' => \fake()->randomElement(['verified', 'pending', 'expired']),
            'risk_level' => \fake()->randomElement(['low', 'medium', 'high']),
            'registration_date' => \fake()->date(),
            'branch_id' => fn() => Branch::inRandomOrder()->value('id') ?? Branch::factory(),
            'relationship_manager_id' => function (array $attributes) {
                $branchId = $attributes['branch_id'] ?? null;
                if ($branchId instanceof \Illuminate\Database\Eloquent\Model) {
                    $branchId = $branchId->id;
                }
                if (is_callable($branchId)) {
                    $branchId = $branchId();
                }

                if (!$branchId) {
                    return null;
                }

                return User::where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->whereIn('role', ['manager', 'csr'])
                    ->inRandomOrder()
                    ->value('id')
                    ?? User::where('branch_id', $branchId)->whereIn('role', ['manager', 'csr'])->inRandomOrder()->value('id');
            },
        ];
    }
}
