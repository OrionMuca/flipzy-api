<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User (if not exists)
        $admin = User::firstOrCreate(
            ['email' => 'admin@flipzy.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command->info('Created admin user: admin@flipzy.com / password');

        // Create Wholesaler Users
        $wholesaler1 = User::firstOrCreate(
            ['email' => 'wholesaler@flipzy.com'],
            [
                'name' => 'John Wholesaler',
                'password' => Hash::make('password'),
            ]
        );
        if (!$wholesaler1->hasRole('wholesaler')) {
            $wholesaler1->assignRole('wholesaler');
        }

        $wholesaler2 = User::firstOrCreate(
            ['email' => 'sarah@flipzy.com'],
            [
                'name' => 'Sarah Property Dealer',
                'password' => Hash::make('password'),
            ]
        );
        if (!$wholesaler2->hasRole('wholesaler')) {
            $wholesaler2->assignRole('wholesaler');
        }

        $this->command->info('Created wholesaler users: wholesaler@flipzy.com, sarah@flipzy.com / password');

        // Create Investor Users
        $investor1 = User::firstOrCreate(
            ['email' => 'investor@flipzy.com'],
            [
                'name' => 'Mike Investor',
                'password' => Hash::make('password'),
            ]
        );
        if (!$investor1->hasRole('investor')) {
            $investor1->assignRole('investor');
        }

        $investor2 = User::firstOrCreate(
            ['email' => 'emma@flipzy.com'],
            [
                'name' => 'Emma Real Estate Investor',
                'password' => Hash::make('password'),
            ]
        );
        if (!$investor2->hasRole('investor')) {
            $investor2->assignRole('investor');
        }

        $investor3 = User::firstOrCreate(
            ['email' => 'david@flipzy.com'],
            [
                'name' => 'David Property Investor',
                'password' => Hash::make('password'),
            ]
        );
        if (!$investor3->hasRole('investor')) {
            $investor3->assignRole('investor');
        }

        $this->command->info('Created investor users: investor@flipzy.com, emma@flipzy.com, david@flipzy.com / password');

        $this->command->info('Total users created: ' . User::count());
    }
}

