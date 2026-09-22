<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Role distribution weights (admin is never generated — only via UserSeeder).
     * manager 30% | csr 25% | compliance 20% | analyst 15% | auditor 10%
     */
    private const ROLE_WEIGHTS = [
        'manager'    => 30,
        'csr'        => 25,
        'compliance' => 20,
        'analyst'    => 15,
        'auditor'    => 10,
    ];

    /**
     * Status distribution weights.
     * active 90% | suspended 5% | pending 5%
     * Suspended is intentionally rare — a suspended branch manager would leave
     * a branch without leadership, which the UserSeeder explicitly prevents.
     */
    private const STATUS_WEIGHTS = [
        'active'    => 90,
        'suspended' => 5,
        'pending'   => 5,
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'role'              => $this->weightedRandom(self::ROLE_WEIGHTS),
            'status'            => $this->weightedRandom(self::STATUS_WEIGHTS),
            'branch_id'         => Branch::inRandomOrder()->first()?->id ?? Branch::factory(),
            'last_login_at'     => $this->faker->optional(0.8)->dateTimeThisYear(),
            'phone'             => $this->faker->phoneNumber(),
        ];
    }

    // -------------------------------------------------------------------------
    // Role state methods
    // -------------------------------------------------------------------------

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => 'manager']);
    }

    public function compliance(): static
    {
        return $this->state(fn () => ['role' => 'compliance']);
    }

    public function analyst(): static
    {
        return $this->state(fn () => ['role' => 'analyst']);
    }

    public function csr(): static
    {
        return $this->state(fn () => ['role' => 'csr']);
    }

    public function auditor(): static
    {
        return $this->state(fn () => ['role' => 'auditor']);
    }

    // -------------------------------------------------------------------------
    // Status state methods
    // -------------------------------------------------------------------------

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    // -------------------------------------------------------------------------
    // Misc state methods
    // -------------------------------------------------------------------------

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Pick a random key from a weighted array.
     * e.g. ['a' => 70, 'b' => 30] → 'a' 70% of the time.
     *
     * @param  array<string, int>  $weights
     */
    private function weightedRandom(array $weights): string
    {
        $total      = array_sum($weights);
        $rand       = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
