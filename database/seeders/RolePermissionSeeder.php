<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Define roles
        $roles = [
            'admin',
            'staff',
            'user',
        ];

        // Define permissions, including 'change blog status'
        $permissions = [
            'view users', 'edit users', 'delete users', 'assign roles', 'view user',
            'create brands', 'edit brands', 'delete brands', 'view brands', 'view brand',
            'change blog status', // New permission
            // Additional permissions here
        ];

        // Create permissions in Permission table
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'permission' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Fetch roles
        $adminRole = Role::where('role_name', 'admin')->first();
        $staffRole = Role::where('role_name', 'staff')->first();

        // Assign 'change blog status' permission to admin and staff
        $changeBlogStatusPermission = Permission::where('permission', 'change blog status')->first();

        if ($adminRole && $changeBlogStatusPermission) {
            $adminRole->permissions()->syncWithoutDetaching([$changeBlogStatusPermission->id]);
        }

        if ($staffRole && $changeBlogStatusPermission) {
            $staffRole->permissions()->syncWithoutDetaching([$changeBlogStatusPermission->id]);
        }
    }
}
