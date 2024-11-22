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
            'manage_surveys', // Quản lý khảo sát
            'manage_questions', // Quản lý câu hỏi
            'view_responses', // Xem câu trả lời
            'manage_blogs', // Quản lý blog
            'confirm_delivery' // Xác nhận đơn hàng
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Tạo role admin
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all()); // Admin có tất cả quyền

        // Tạo role staff
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->givePermissionTo([
            'manage_questions',
            'view_responses',
        ]); // Staff chỉ có một số quyền
    }
}
