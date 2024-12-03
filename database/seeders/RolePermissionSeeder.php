<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Tạo các quyền
        $permissions = [
            'manage_surveys',
            'manage_questions',
            'view_responses',
            'manage_blogs',
            'confirm_delivery'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Tạo role admin
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        // Tạo role staff
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->givePermissionTo([
            'manage_questions',
            'view_responses',
        ]);
    }
}
