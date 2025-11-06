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
        // Seed roles and permissions first
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Seed subscription plans
        $this->call([
            SubscriptionPlanSeeder::class,
        ]);

        // Seed users with roles
        $this->call([
            UserSeeder::class,
        ]);

        // Seed properties (requires wholesalers)
        $this->call([
            PropertySeeder::class,
        ]);
    }
}
