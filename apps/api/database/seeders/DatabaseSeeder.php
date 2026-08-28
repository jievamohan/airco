<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            CatalogSeeder::class,
            SequenceSeeder::class,
        ]);

        $email = (string) config('agent.owner.email');

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('agent.owner.name'),
                'role' => 'owner',
                'password' => Hash::make((string) config('agent.owner.initial_password')),
            ],
        );
    }
}
