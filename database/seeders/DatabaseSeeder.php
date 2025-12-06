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
        $this->command->info('🚀 Starting database seeding...');
        $this->command->newLine();

        // Seed roles and permissions first
        $this->call([
            RolePermissionSeeder::class,
        ]);
        $this->command->newLine();

        // Seed subscription plans
        $this->call([
            SubscriptionPlanSeeder::class,
        ]);
        $this->command->newLine();

        // Seed users with roles
        $this->call([
            UserSeeder::class,
        ]);

        // Seed properties (requires wholesalers)
        $this->call([
            PropertySeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->comment('📝 Quick Login Reference:');
        $this->command->line('   Admin:     admin@flipzy.com / password');
        $this->command->line('   Wholesaler: wholesaler@flipzy.com / password');
        $this->command->line('   Investor:   investor@flipzy.com / password');
        $this->command->newLine();
    }
}
