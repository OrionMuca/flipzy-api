<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
            
            // Message permissions
            'messages.send',
            'messages.view',
            
            // Analytics permissions
            'analytics.view',
            'analytics.view-all',
            
            // Rehab estimate permissions
            'estimates.generate',
            'estimates.view',
            
            // Admin permissions
            'admin.users.manage',
            'admin.properties.manage',
            'admin.subscriptions.manage',
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[] = Permission::firstOrCreate(['name' => $permission]);
        }

        // Clear cache after creating permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and assign permissions
        $investor = Role::firstOrCreate(['name' => 'investor']);
        $investor->syncPermissions([
            'properties.view',
            'properties.list',
            'messages.send',
            'messages.view',
            'analytics.view',
            'estimates.view', // Can view estimates (access controlled by subscription)
        ]);

        $wholesaler = Role::firstOrCreate(['name' => 'wholesaler']);
        $wholesaler->syncPermissions([
            'properties.create',
            'properties.view',
            'properties.update',
            'properties.delete',
            'properties.list',
            'messages.send',
            'messages.view',
            'analytics.view',
        ]);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());
    }
}

