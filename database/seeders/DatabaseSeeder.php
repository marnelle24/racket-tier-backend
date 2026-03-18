<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => User::ROLE_USER,
        ]);

        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        if (is_string($adminEmail) && $adminEmail !== '' && is_string($adminPassword) && $adminPassword !== '') {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Administrator',
                    'password' => $adminPassword,
                    'role' => User::ROLE_ADMIN,
                ]
            );
        }
    }
}
