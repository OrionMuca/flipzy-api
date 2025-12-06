<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding users for development/testing...');
        $this->command->newLine();

        // Create Admin User (if not exists)
        $admin = User::firstOrCreate(
            ['email' => 'admin@flipzy.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        
        // Update email_verified_at if user already exists but not verified
        if (!$admin->email_verified_at) {
            $admin->email_verified_at = Carbon::now();
            $admin->save();
        }
        
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command->info('✅ Admin User');
        $this->command->line('   Email: admin@flipzy.com');
        $this->command->line('   Password: password');
        $this->command->line('   Role: admin');
        $this->command->line('   Email Verified: Yes');
        $this->command->newLine();

        // Create Wholesaler Users
        $wholesaler1 = User::firstOrCreate(
            ['email' => 'wholesaler@flipzy.com'],
            [
                'name' => 'John Wholesaler',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$wholesaler1->email_verified_at) {
            $wholesaler1->email_verified_at = Carbon::now();
            $wholesaler1->save();
        }
        if (!$wholesaler1->hasRole('wholesaler')) {
            $wholesaler1->assignRole('wholesaler');
        }

        $wholesaler2 = User::firstOrCreate(
            ['email' => 'sarah@flipzy.com'],
            [
                'name' => 'Sarah Property Dealer',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$wholesaler2->email_verified_at) {
            $wholesaler2->email_verified_at = Carbon::now();
            $wholesaler2->save();
        }
        if (!$wholesaler2->hasRole('wholesaler')) {
            $wholesaler2->assignRole('wholesaler');
        }

        $this->command->info('✅ Wholesaler Users');
        $this->command->line('   Email: wholesaler@flipzy.com | Password: password');
        $this->command->line('   Email: sarah@flipzy.com | Password: password');
        $this->command->line('   Role: wholesaler');
        $this->command->line('   Email Verified: Yes');
        $this->command->newLine();

        // Create Investor Users
        $investor1 = User::firstOrCreate(
            ['email' => 'investor@flipzy.com'],
            [
                'name' => 'Mike Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$investor1->email_verified_at) {
            $investor1->email_verified_at = Carbon::now();
            $investor1->save();
        }
        if (!$investor1->hasRole('investor')) {
            $investor1->assignRole('investor');
        }

        $investor2 = User::firstOrCreate(
            ['email' => 'emma@flipzy.com'],
            [
                'name' => 'Emma Real Estate Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$investor2->email_verified_at) {
            $investor2->email_verified_at = Carbon::now();
            $investor2->save();
        }
        if (!$investor2->hasRole('investor')) {
            $investor2->assignRole('investor');
        }

        $investor3 = User::firstOrCreate(
            ['email' => 'david@flipzy.com'],
            [
                'name' => 'David Property Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$investor3->email_verified_at) {
            $investor3->email_verified_at = Carbon::now();
            $investor3->save();
        }
        if (!$investor3->hasRole('investor')) {
            $investor3->assignRole('investor');
        }

        $this->command->info('✅ Investor Users');
        $this->command->line('   Email: investor@flipzy.com | Password: password');
        $this->command->line('   Email: emma@flipzy.com | Password: password');
        $this->command->line('   Email: david@flipzy.com | Password: password');
        $this->command->line('   Role: investor');
        $this->command->line('   Email Verified: Yes');
        $this->command->newLine();

        $this->command->info('📊 Summary:');
        $this->command->line('   Total users: ' . User::count());
        $this->command->line('   Admin users: ' . User::role('admin')->count());
        $this->command->line('   Wholesaler users: ' . User::role('wholesaler')->count());
        $this->command->line('   Investor users: ' . User::role('investor')->count());
        $this->command->newLine();
        $this->command->info('✨ All users seeded successfully!');
    }
}

