<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Beheerder',
            'email' => $this->faker->unique()->safeEmail(),
            'role' => 'owner',
            'password' => Hash::make('geheim-wachtwoord'),
        ];
    }
}
