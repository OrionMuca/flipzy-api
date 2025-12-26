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
        
        // Always update password and email_verified_at
        $admin->password = Hash::make('password');
        if (!$admin->email_verified_at) {
            $admin->email_verified_at = Carbon::now();
        }
        $admin->save();
        
        // Find role by name and guard, then assign
        $adminRole = \App\Models\Role::where('name', 'admin')
            ->where('guard_name', 'api')
            ->first();
        
        if ($adminRole && !$admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
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
        $wholesaler1->password = Hash::make('password');
        if (!$wholesaler1->email_verified_at) {
            $wholesaler1->email_verified_at = Carbon::now();
        }
        $wholesaler1->save();
        
        $wholesalerRole = \App\Models\Role::where('name', 'wholesaler')
            ->where('guard_name', 'api')
            ->first();
        
        if ($wholesalerRole && !$wholesaler1->hasRole($wholesalerRole)) {
            $wholesaler1->assignRole($wholesalerRole);
        }

        $wholesaler2 = User::firstOrCreate(
            ['email' => 'sarah@flipzy.com'],
            [
                'name' => 'Sarah Property Dealer',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        $wholesaler2->password = Hash::make('password');
        if (!$wholesaler2->email_verified_at) {
            $wholesaler2->email_verified_at = Carbon::now();
        }
        $wholesaler2->save();
        
        if ($wholesalerRole && !$wholesaler2->hasRole($wholesalerRole)) {
            $wholesaler2->assignRole($wholesalerRole);
        }

        $this->command->info('✅ Wholesaler Users');
        $this->command->line('   Email: wholesaler@flipzy.com | Password: password');
        $this->command->line('   Email: sarah@flipzy.com | Password: password');
        $this->command->line('   Role: wholesaler');
        $this->command->line('   Email Verified: Yes');
        $this->command->newLine();

        // Create Investor Users
        $investorRole = \App\Models\Role::where('name', 'investor')
            ->where('guard_name', 'api')
            ->first();

        $investor1 = User::firstOrCreate(
            ['email' => 'investor@flipzy.com'],
            [
                'name' => 'Mike Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        $investor1->password = Hash::make('password');
        if (!$investor1->email_verified_at) {
            $investor1->email_verified_at = Carbon::now();
        }
        $investor1->save();
        
        if ($investorRole && !$investor1->hasRole($investorRole)) {
            $investor1->assignRole($investorRole);
        }

        $investor2 = User::firstOrCreate(
            ['email' => 'emma@flipzy.com'],
            [
                'name' => 'Emma Real Estate Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        $investor2->password = Hash::make('password');
        if (!$investor2->email_verified_at) {
            $investor2->email_verified_at = Carbon::now();
        }
        $investor2->save();
        
        if ($investorRole && !$investor2->hasRole($investorRole)) {
            $investor2->assignRole($investorRole);
        }

        $investor3 = User::firstOrCreate(
            ['email' => 'david@flipzy.com'],
            [
                'name' => 'David Property Investor',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        $investor3->password = Hash::make('password');
        if (!$investor3->email_verified_at) {
            $investor3->email_verified_at = Carbon::now();
        }
        $investor3->save();
        
        if ($investorRole && !$investor3->hasRole($investorRole)) {
            $investor3->assignRole($investorRole);
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
        $this->command->line('   Admin users: ' . User::role('admin', 'api')->count());
        $this->command->line('   Wholesaler users: ' . User::role('wholesaler', 'api')->count());
        $this->command->line('   Investor users: ' . User::role('investor', 'api')->count());
        $this->command->newLine();
        $this->command->info('✨ All users seeded successfully!');
    }
}