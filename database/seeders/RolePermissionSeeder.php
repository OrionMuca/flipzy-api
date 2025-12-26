<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Property permissions
            'properties.create',
            'properties.view',
            'properties.update',
            'properties.delete',
            'properties.list',
            'properties.images.upload',
            'properties.images.delete',
            'properties.images.set-primary',
            'properties.enrich',
            
            // Message permissions
            'messages.send',
            'messages.view',
            
            // Conversation permissions
            'conversations.create',
            'conversations.view',
            'conversations.list',
            
            // Analytics permissions
            'analytics.view',
            'analytics.view-all',
            'analytics.track.view',
            'analytics.track.save',
            'analytics.track.inquiry',
            'analytics.credibility',
            'analytics.my-analytics',
            
            // Rehab estimate permissions
            'estimates.generate',
            'estimates.view',
            
            // Payment permissions
            'payments.create',
            'payments.view',
            
            // Refund permissions
            'refunds.create',
            'refunds.view',
            
            // Subscription permissions
            'subscriptions.view-plans',
            'subscriptions.checkout',
            'subscriptions.create',
            'subscriptions.view-current',
            'subscriptions.cancel',
            'subscriptions.view-history',
            
            // Notification permissions
            'notifications.view',
            'notifications.mark-read',
            'notifications.delete',
            
            // Admin permissions
            'admin.users.manage',
            'admin.properties.manage',
            'admin.subscriptions.manage',
            'admin.notifications.send',
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[] = Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Clear cache after creating permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and assign permissions (using 'api' guard)
        // Note: We're keeping the original role-based permissions for now
        // These can be expanded later when implementing permission checks
        
        $investor = Role::firstOrCreate([
            'name' => 'investor',
            'guard_name' => 'api'
        ]);
        $investor->syncPermissions([
            'properties.view',
            'properties.list',
            'messages.send',
            'messages.view',
            'conversations.create',
            'conversations.view',
            'conversations.list',
            'analytics.view',
            'analytics.track.view',
            'analytics.track.save',
            'analytics.track.inquiry',
            'estimates.view',
            'payments.create',
            'payments.view',
            'refunds.create',
            'refunds.view',
            'subscriptions.view-plans',
            'subscriptions.checkout',
            'subscriptions.create',
            'subscriptions.view-current',
            'subscriptions.cancel',
            'subscriptions.view-history',
            'notifications.view',
            'notifications.mark-read',
            'notifications.delete',
        ]);

        $wholesaler = Role::firstOrCreate([
            'name' => 'wholesaler',
            'guard_name' => 'api'
        ]);
        $wholesaler->syncPermissions([
            'properties.create',
            'properties.view',
            'properties.update',
            'properties.delete',
            'properties.list',
            'properties.images.upload',
            'properties.images.delete',
            'properties.images.set-primary',
            'properties.enrich',
            'messages.send',
            'messages.view',
            'conversations.create',
            'conversations.view',
            'conversations.list',
            'analytics.view',
            'analytics.track.view',
            'analytics.credibility',
            'analytics.my-analytics',
            'payments.create',
            'payments.view',
            'subscriptions.view-plans',
            'subscriptions.checkout',
            'subscriptions.create',
            'subscriptions.view-current',
            'subscriptions.cancel',
            'subscriptions.view-history',
            'notifications.view',
            'notifications.mark-read',
            'notifications.delete',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        // Clear cache after assigning permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles and permissions seeded successfully!');
        $this->command->line('   Created ' . count($permissions) . ' permissions');
        $this->command->line('   Created 3 roles: admin, wholesaler, investor');
        $this->command->line('   Admin role has all permissions');
        $this->command->newLine();
        $this->command->info('📝 Note: Controllers currently use role checks (hasRole).');
        $this->command->line('   Permissions are defined and can be used later for granular control.');
    }
}